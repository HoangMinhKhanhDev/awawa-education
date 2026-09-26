<?php

namespace App\Services\Notebook;

use App\Enums\ArtifactType;
use App\Models\NotebookArtifact;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArtifactExporter
{
    public function downloadDocx(NotebookArtifact $artifact): BinaryFileResponse
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Aptos');
        $section = $word->addSection();
        $section->addTitle($artifact->title, 1);

        foreach ($this->contentLines($artifact) as $line) {
            if (preg_match('/^(#{1,3})\s+(.+)$/u', $line, $matches) === 1) {
                $section->addTitle($matches[2], min(3, mb_strlen($matches[1]) + 1));

                continue;
            }

            if (str_starts_with($line, '- ') || str_starts_with($line, '* ')) {
                $section->addText('• '.mb_substr($line, 2));

                continue;
            }

            $section->addText($line === '' ? ' ' : $line);
        }

        $path = tempnam(sys_get_temp_dir(), 'awawa-notebook-');

        if ($path === false) {
            throw new RuntimeException('Không tạo được tệp DOCX tạm.');
        }

        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
        } catch (\Throwable $exception) {
            @unlink($path);

            throw new RuntimeException('Không tạo được tệp DOCX: '.$exception->getMessage(), previous: $exception);
        }

        $filename = (Str::slug($artifact->title) ?: 'notebook').'.docx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array<int, string>
     */
    protected function contentLines(NotebookArtifact $artifact): array
    {
        $type = ArtifactType::from($artifact->type);

        if (! $type->isJson()) {
            return preg_split('/\R/u', (string) $artifact->text_content) ?: [];
        }

        $lines = [];

        if ($type === ArtifactType::Questions) {
            foreach ($artifact->payload['items'] ?? [] as $index => $item) {
                $this->appendQuestion($lines, $index + 1, $item);
            }
        } elseif ($type === ArtifactType::Exam) {
            if (filled($artifact->payload['description'] ?? null)) {
                $lines[] = (string) $artifact->payload['description'];
            }

            foreach ($artifact->payload['sections'] ?? [] as $section) {
                $lines[] = '## '.(string) ($section['title'] ?? 'Phần');

                foreach ($section['questions'] ?? [] as $index => $item) {
                    $this->appendQuestion($lines, $index + 1, $item);
                }
            }
        } elseif ($type === ArtifactType::Flashcards) {
            foreach ($artifact->payload['cards'] ?? [] as $index => $card) {
                $lines[] = '## Thẻ '.($index + 1);
                $lines[] = 'Mặt trước: '.(string) ($card['front'] ?? '');
                $lines[] = 'Mặt sau: '.(string) ($card['back'] ?? '');
                $lines[] = '';
            }
        } else {
            foreach ($artifact->payload['nodes'] ?? [] as $node) {
                $lines[] = '- '.(string) ($node['label'] ?? '');
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $question
     */
    protected function appendQuestion(array &$lines, int $index, array $question): void
    {
        $lines[] = $index.'. '.(string) ($question['content'] ?? '');

        foreach ($question['options'] ?? [] as $option) {
            $lines[] = '   '.(! empty($option['is_correct']) ? '✓ ' : '– ').(string) ($option['content'] ?? '');
        }

        if (filled($question['answer'] ?? null)) {
            $lines[] = 'Đáp án: '.(string) $question['answer'];
        }

        if (filled($question['explanation'] ?? null)) {
            $lines[] = 'Giải thích: '.(string) $question['explanation'];
        }

        $lines[] = '';
    }
}
