<?php

namespace App\Services\Notebook;

use App\Models\Document;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookChunk;
use App\Models\NotebookSource;
use App\Models\Question;
use App\Services\Notebook\HighlightPicker as HighlightPickerService;
use App\Support\NotebookConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Nhận nguồn (tệp/văn bản/tài liệu/câu hỏi/đề/web) → trích văn bản → cắt đoạn.
 */
class SourceIngestor
{
    public function fromText(Notebook $notebook, string $title, string $text, string $type = 'text', array $extra = []): NotebookSource
    {
        $source = $this->createSource($notebook, array_merge([
            'type' => $type,
            'title' => $title,
            'status' => 'processing',
        ], $extra));

        $this->storeText($source, $this->normalize($type === 'web' ? app(WebPageCleaner::class)->clean($text) : $text));

        return $source->refresh();
    }

    public function fromUpload(Notebook $notebook, UploadedFile $file, ?string $title = null): NotebookSource
    {
        $path = $file->store("notebook/{$notebook->id}", 'public');

        $source = $this->createSource($notebook, [
            'type' => 'file',
            'title' => $title ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'status' => 'processing',
        ]);

        $this->extractAndStore(
            $source,
            Storage::disk('public')->path($path),
            (string) $file->getClientOriginalName(),
            (string) $file->getMimeType(),
        );

        return $source->refresh();
    }

    public function fromDocument(Notebook $notebook, Document $document): NotebookSource
    {
        $source = $this->createSource($notebook, [
            'type' => 'document',
            'title' => $document->title,
            'ref_type' => $document->getMorphClass(),
            'ref_id' => $document->getKey(),
            'file_path' => $document->file_path,
            'original_name' => $document->original_name,
            'mime' => $document->mime,
            'size' => (int) $document->size,
            'status' => 'processing',
        ]);

        $absolute = Storage::disk('public')->path($document->file_path);

        $this->extractAndStore($source, $absolute, (string) $document->original_name, (string) $document->mime);

        return $source->refresh();
    }

    public function fromQuestion(Notebook $notebook, Question $question): NotebookSource
    {
        $source = $this->createSource($notebook, [
            'type' => 'question',
            'title' => mb_substr($question->content, 0, 80),
            'ref_type' => $question->getMorphClass(),
            'ref_id' => $question->getKey(),
            'status' => 'processing',
        ]);

        $this->storeText($source, $this->normalize($this->questionToText($question)));

        return $source->refresh();
    }

    public function fromExam(Notebook $notebook, Exam $exam): NotebookSource
    {
        $source = $this->createSource($notebook, [
            'type' => 'exam',
            'title' => $exam->title,
            'ref_type' => $exam->getMorphClass(),
            'ref_id' => $exam->getKey(),
            'status' => 'processing',
        ]);

        $this->storeText($source, $this->normalize($this->examToText($exam)));

        return $source->refresh();
    }

    public function retry(NotebookSource $source, ?string $replacementText = null): NotebookSource
    {
        $source->forceFill(['status' => 'processing', 'error' => null])->save();

        if ($replacementText !== null) {
            $text = $source->type === 'web'
                ? app(WebPageCleaner::class)->clean($replacementText)
                : $replacementText;

            $this->storeText($source, $this->normalize($text));

            return $source->refresh();
        }

        if (in_array($source->type, ['file', 'document'], true)) {
            if (blank($source->file_path)) {
                return $this->markFailed($source, 'Không tìm thấy tệp nguồn để thử lại.');
            }

            $absolutePath = Storage::disk('public')->path($source->file_path);

            $this->extractAndStore($source, $absolutePath, (string) $source->original_name, (string) $source->mime);

            return $source->refresh();
        }

        if (in_array($source->type, ['text', 'web'], true)) {
            if (blank($source->raw_content)) {
                return $this->markFailed($source, 'Nguồn không còn nội dung để thử lại.');
            }

            $this->storeText($source, (string) $source->raw_content);

            return $source->refresh();
        }

        $reference = $source->ref;

        if ($source->type === 'question' && $reference instanceof Question) {
            $this->storeText($source, $this->normalize($this->questionToText($reference)));

            return $source->refresh();
        }

        if ($source->type === 'exam' && $reference instanceof Exam) {
            $this->storeText($source, $this->normalize($this->examToText($reference)));

            return $source->refresh();
        }

        return $this->markFailed($source, 'Không tìm thấy dữ liệu gốc của nguồn để thử lại.');
    }

    public function remove(NotebookSource $source): void
    {
        if ($source->file_path !== null && ! $source->ref_id) {
            Storage::disk('public')->delete($source->file_path);
        }

        $source->delete();
    }

    protected function createSource(Notebook $notebook, array $attributes): NotebookSource
    {
        $order = (int) $notebook->sources()->max('order') + 1;

        return $notebook->sources()->create($attributes + ['order' => $order]);
    }

    protected function extractAndStore(NotebookSource $source, string $absolutePath, string $name, string $mime): void
    {
        try {
            if (! is_file($absolutePath)) {
                throw new RuntimeException('Không tìm thấy tệp nguồn.');
            }

            $text = $this->extractFile($absolutePath, $name, $mime);

            $this->storeText($source, $this->normalize($text));
        } catch (\Throwable $exception) {
            $source->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();
        }
    }

    protected function storeText(NotebookSource $source, string $text): void
    {
        if ($text === '') {
            $source->forceFill(['raw_content' => ''])->save();
            $this->markFailed($source, 'Không trích được nội dung văn bản từ nguồn này.');

            return;
        }

        $limit = NotebookConfig::maxSourceChars();

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit);
        }

        NotebookChunk::query()->where('source_id', $source->id)->delete();

        $created = [];

        foreach ($this->chunkText($text) as $position => $chunk) {
            $created[] = NotebookChunk::create([
                'notebook_id' => $source->notebook_id,
                'source_id' => $source->id,
                'position' => $position,
                'content' => $chunk['content'],
                'char_start' => $chunk['start'],
                'char_end' => $chunk['end'],
            ]);
        }

        $highlightIds = app(HighlightPickerService::class)->highlightedChunkIds(
            array_map(fn (NotebookChunk $chunk): array => [
                'id' => $chunk->id,
                'position' => $chunk->position,
                'content' => (string) $chunk->content,
            ], $created),
        );

        if ($highlightIds !== []) {
            NotebookChunk::query()->whereIn('id', $highlightIds)->update(['is_highlight' => true]);
        }

        $source->forceFill([
            'status' => 'ready',
            'error' => null,
            'raw_content' => $text,
            'char_count' => mb_strlen($text),
        ])->save();
    }

    protected function markFailed(NotebookSource $source, string $message): NotebookSource
    {
        $source->forceFill([
            'status' => 'failed',
            'error' => mb_substr($message, 0, 500),
        ])->save();

        return $source->refresh();
    }

    /**
     * @return array<int, array{start: int, end: int, content: string}>
     */
    protected function chunkText(string $text): array
    {
        $size = NotebookConfig::chunkSize();
        $overlap = NotebookConfig::chunkOverlap();
        $length = mb_strlen($text);

        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $end = min($length, $start + $size);

            $chunks[] = [
                'start' => $start,
                'end' => $end,
                'content' => trim(mb_substr($text, $start, $end - $start)),
            ];

            if ($end >= $length) {
                break;
            }

            $next = $end - $overlap;
            $start = $next > $start ? $next : $end;
        }

        return $chunks;
    }

    protected function extractFile(string $absolutePath, string $name, string $mime): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, ['txt', 'md', 'markdown', 'csv', 'html', 'htm'], true) || str_starts_with($mime, 'text/')) {
            if (in_array($extension, ['html', 'htm'], true)) {
                return trim(strip_tags((string) file_get_contents($absolutePath)));
            }

            return (string) file_get_contents($absolutePath);
        }

        if ($extension === 'pdf' || str_contains($mime, 'pdf')) {
            return (new PdfParser)->parseFile($absolutePath)->getText();
        }

        if ($extension === 'docx') {
            return $this->extractDocx($absolutePath);
        }

        throw new RuntimeException('Định dạng tệp chưa hỗ trợ. Hãy dùng PDF, DOCX, TXT hoặc MD.');
    }

    protected function extractDocx(string $absolutePath): string
    {
        $phpWord = PhpWordIOFactory::load($absolutePath);

        $text = '';

        foreach ($phpWord->getSections() as $section) {
            $text .= $this->containerText($section)."\n";
        }

        return $text;
    }

    protected function containerText(AbstractContainer $container): string
    {
        $text = '';

        foreach ($container->getElements() as $element) {
            $text .= $this->elementText($element);
        }

        return $text;
    }

    protected function elementText(AbstractElement $element): string
    {
        if ($element instanceof AbstractContainer) {
            return $this->containerText($element)."\n";
        }

        if (method_exists($element, 'getText')) {
            return (string) $element->getText()."\n";
        }

        return '';
    }

    protected function questionToText(Question $question): string
    {
        $lines = [
            'Câu hỏi ('.$question->type->label().' — độ khó '.$question->difficulty->label().' — '.(float) $question->points.' điểm)',
            $question->content,
        ];

        if ($question->type->hasOptions()) {
            foreach ($question->options as $index => $option) {
                $lines[] = chr(65 + $index).'. '.$option->content.($option->is_correct ? ' (đáp án đúng)' : '');
            }
        }

        if (filled($question->answer)) {
            $lines[] = 'Đáp án: '.$question->answer;
        }

        if (filled($question->explanation)) {
            $lines[] = 'Giải thích: '.$question->explanation;
        }

        return implode("\n", $lines);
    }

    protected function examToText(Exam $exam): string
    {
        $lines = [
            $exam->type->label().': '.$exam->title,
            (string) $exam->description,
        ];

        foreach ($exam->examQuestions()->with(['question.options', 'section'])->get() as $index => $examQuestion) {
            $question = $examQuestion->question;

            if ($question === null) {
                continue;
            }

            if ($examQuestion->section) {
                $lines[] = '— '.$examQuestion->section->title;
            }

            $lines[] = ($index + 1).'. '.$this->questionToText($question);
        }

        return implode("\n\n", array_filter($lines, fn ($line) => $line !== null && $line !== ''));
    }

    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
