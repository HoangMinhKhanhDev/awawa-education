<?php

namespace App\Services\Notebook;

use App\Enums\AiPurpose;
use App\Enums\ArtifactType;
use App\Models\NotebookArtifact;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use RuntimeException;

/**
 * Sửa cục bộ bản nháp theo yêu cầu (“làm khó câu 3 và 7 lên”) thay vì soạn lại
 * toàn bộ từ đầu.
 *
 * AI chỉ trả về phần đổi, kèm số thứ tự đúng như giáo viên nhìn thấy (Câu 1,
 * Câu 2...). Mỗi đề xuất đều chụp lại giá trị cũ để màn duyệt hiện được diff,
 * và khi áp vào chỉ chạm đúng những mục đó. Nội dung văn xuôi (tài liệu, đề
 * cương, bản tin) thì AI trả về cặp tìm–thay thế thay vì viết lại cả bài.
 */
class ArtifactRefiner
{
    public function __construct(
        protected AiManager $ai,
    ) {}

    /**
     * Xin AI đề xuất sửa, chưa áp vào bản nháp.
     *
     * @return array{summary: string, edits: array<int, array<string, mixed>>, skipped: array<int, string>, tokens: int, provider: string, model: string}
     */
    public function refine(NotebookArtifact $artifact, string $instruction, ?int $userId = null): array
    {
        $type = ArtifactType::from($artifact->type);
        $numbered = $this->numberedContent($artifact, $type);

        if ($numbered['count'] === 0) {
            throw new RuntimeException('Bản nháp chưa có nội dung để sửa.');
        }

        $pinned = $this->ai->pinnedSelection(
            $artifact->notebook->settings['ai_provider'] ?? null,
            $artifact->notebook->settings['ai_model'] ?? null,
        );

        $system = $this->buildSystem($type, $numbered['text']);
        $user = 'Yêu cầu của giáo viên: '.$instruction;

        try {
            $result = $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], [
                'purpose' => AiPurpose::Artifact,
                'subject_id' => $artifact->subject_id,
                'user_id' => $userId ?? $artifact->user_id,
                'provider_key' => $pinned['provider_key'],
                'model' => $pinned['model'],
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (AiException $exception) {
            if (! $this->rejectsJsonMode($exception)) {
                throw $exception;
            }

            $result = $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], [
                'purpose' => AiPurpose::Artifact,
                'subject_id' => $artifact->subject_id,
                'user_id' => $userId ?? $artifact->user_id,
                'provider_key' => $pinned['provider_key'],
                'model' => $pinned['model'],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);
        }

        $decoded = $this->decodeJson($result->text);

        $summary = is_string($decoded['summary'] ?? null) && trim((string) $decoded['summary']) !== ''
            ? trim((string) $decoded['summary'])
            : 'AI đã đề xuất chỉnh sửa.';

        ['edits' => $edits, 'skipped' => $skipped] = $type->isJson()
            ? $this->validateItemEdits($artifact, $type, (array) ($decoded['edits'] ?? []))
            : $this->validateReplacements($artifact, (array) ($decoded['replacements'] ?? []));

        if ($edits === []) {
            throw new RuntimeException(
                'AI không sửa được mục nào. '.($skipped !== [] ? implode(' ', $skipped) : 'Hãy nêu rõ số câu hoặc đoạn cần sửa.'),
            );
        }

        return [
            'summary' => $summary,
            'edits' => $edits,
            'skipped' => $skipped,
            'tokens' => $result->totalTokens(),
            'provider' => $result->providerKey,
            'model' => $result->model,
        ];
    }

    /**
     * Áp đề xuất đã duyệt vào bản nháp. Chỉ chạm đúng mục được sửa.
     *
     * @param  array<int, array<string, mixed>>  $edits
     * @return array{applied: int, skipped: array<int, string>}
     */
    public function apply(NotebookArtifact $artifact, array $edits): array
    {
        $type = ArtifactType::from($artifact->type);
        $applied = 0;
        $skipped = [];

        if ($type->isJson()) {
            $payload = $artifact->payload ?? [];
            $map = $this->itemMap($payload, $type);

            foreach ($edits as $edit) {
                $index = (int) ($edit['index'] ?? 0);

                if (! isset($map[$index])) {
                    $skipped[] = "Mục số {$index} không còn tồn tại nên bỏ qua.";

                    continue;
                }

                $this->writeItem($payload, $type, $map[$index], (array) ($edit['new'] ?? []));
                $applied++;
            }

            $artifact->update(['payload' => $payload]);
        } else {
            $text = (string) $artifact->text_content;

            foreach ($edits as $edit) {
                $find = (string) ($edit['find'] ?? '');
                $replace = (string) ($edit['replace'] ?? '');

                if ($find === '' || ! str_contains($text, $find)) {
                    $skipped[] = 'Một đoạn cần thay không còn khớp với bản nháp nên bỏ qua.';

                    continue;
                }

                $text = $this->replaceFirst($text, $find, $replace);
                $applied++;
            }

            $artifact->update(['text_content' => $text]);
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Trình bày nội dung hiện tại đánh số đúng như giáo viên nhìn thấy.
     *
     * @return array{text: string, count: int}
     */
    protected function numberedContent(NotebookArtifact $artifact, ArtifactType $type): array
    {
        $payload = $artifact->payload ?? [];

        if (! $type->isJson()) {
            $text = trim((string) $artifact->text_content);

            return ['text' => $text, 'count' => $text === '' ? 0 : 1];
        }

        $lines = [];
        $count = 0;

        foreach ($this->flatItems($payload, $type) as $entry) {
            $count++;
            $lines[] = $entry['label'].': '.$entry['text'];
        }

        return ['text' => implode("\n\n", $lines), 'count' => $count];
    }

    /**
     * Trải phẳng các mục kèm nhãn hiển thị và đường dẫn để ghi lại.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, text: string, path: array<int|string>}>
     */
    protected function flatItems(array $payload, ArtifactType $type): array
    {
        $out = [];

        if ($type === ArtifactType::Exam) {
            $number = 0;

            foreach (array_values((array) ($payload['sections'] ?? [])) as $sectionIndex => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $sectionTitle = trim((string) ($section['title'] ?? 'Phần '.($sectionIndex + 1)));

                foreach (array_values((array) ($section['questions'] ?? [])) as $questionIndex => $question) {
                    if (! is_array($question)) {
                        continue;
                    }

                    $number++;
                    $out[$number] = [
                        'label' => $sectionTitle.' — Câu '.$number,
                        'text' => $this->itemText($question, $type),
                        'path' => [$sectionIndex, $questionIndex],
                    ];
                }
            }

            return $out;
        }

        $key = match ($type) {
            ArtifactType::Questions => 'items',
            ArtifactType::Flashcards => 'cards',
            ArtifactType::MindMap => 'nodes',
            default => 'items',
        };

        $label = match ($type) {
            ArtifactType::Flashcards => 'Thẻ',
            ArtifactType::MindMap => 'Nút',
            default => 'Câu',
        };

        foreach (array_values((array) ($payload[$key] ?? [])) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $number = $index + 1;
            $out[$number] = [
                'label' => $label.' '.$number,
                'text' => $this->itemText($item, $type),
                'path' => [$key, $index],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function itemText(array $item, ArtifactType $type): string
    {
        if ($type === ArtifactType::Flashcards) {
            return trim((string) ($item['front'] ?? '')).'  →  '.trim((string) ($item['back'] ?? ''));
        }

        if ($type === ArtifactType::MindMap) {
            return trim((string) ($item['label'] ?? $item['title'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)));
        }

        $text = trim((string) ($item['content'] ?? ''));

        foreach ((array) ($item['options'] ?? []) as $position => $option) {
            if (! is_array($option) || trim((string) ($option['content'] ?? '')) === '') {
                continue;
            }

            $mark = ! empty($option['is_correct']) ? ' (đúng)' : '';
            $text .= "\n  ".chr(97 + (int) $position).') '.trim((string) $option['content']).$mark;
        }

        if (trim((string) ($item['answer'] ?? '')) !== '') {
            $text .= "\n  Đáp án: ".trim((string) $item['answer']);
        }

        return $text;
    }

    protected function buildSystem(ArtifactType $type, string $numbered): string
    {
        $target = $type->isJson()
            ? 'Chỉ trả về đúng một JSON: {"summary": "mô tả ngắn đã sửa gì", "edits": [{"index": số thứ tự của mục cần sửa, rồi toàn bộ nội dung mới của mục đó giữ nguyên cấu trúc cũ}]}. '
                .'KHÔNG trả về mục không đổi, KHÔNG đánh lại số, KHÔNG giải thích ngoài JSON.'
            : 'Chỉ trả về đúng một JSON: {"summary": "mô tả ngắn đã sửa gì", "replacements": [{"find": "đoạn văn HIỆN CÓ cần sửa, chép nguyên văn", "replace": "đoạn văn mới thay vào"}]}. '
                .'Mỗi cặp chỉ sửa một chỗ, KHÔNG viết lại cả bài, KHÔNG giải thích ngoài JSON.';

        return 'Bạn là trợ lý soạn bài cho giáo viên, sửa nội dung có sẵn theo yêu cầu. '
            .$target."\n\n=== NỘI DUNG HIỆN TẠI ===\n".$numbered;
    }

    /**
     * @return array{edits: array<int, array<string, mixed>>, skipped: array<int, string>}
     */
    protected function validateItemEdits(NotebookArtifact $artifact, ArtifactType $type, array $raw): array
    {
        $payload = $artifact->payload ?? [];
        $map = $this->itemMap($payload, $type);
        $edits = [];
        $skipped = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $index = (int) ($entry['index'] ?? 0);

            if (! isset($map[$index])) {
                $skipped[] = "Mục số {$index} không tồn tại nên bỏ qua.";

                continue;
            }

            $new = $entry;
            unset($new['index']);

            if (! $this->hasContent($new, $type)) {
                $skipped[] = "Mục số {$index} thiếu nội dung mới nên bỏ qua.";

                continue;
            }

            $edits[] = [
                'index' => $index,
                'label' => $map[$index]['label'],
                'old' => $map[$index]['item'],
                'new' => $new,
            ];
        }

        return ['edits' => $edits, 'skipped' => $skipped];
    }

    /**
     * @return array{edits: array<int, array<string, mixed>>, skipped: array<int, string>}
     */
    protected function validateReplacements(NotebookArtifact $artifact, array $raw): array
    {
        $text = (string) $artifact->text_content;
        $edits = [];
        $skipped = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $find = (string) ($entry['find'] ?? '');
            $replace = (string) ($entry['replace'] ?? '');

            if ($find === '' || $replace === '' || $find === $replace) {
                $skipped[] = 'Một cặp thay thế trống hoặc giống hệt nên bỏ qua.';

                continue;
            }

            if (! str_contains($text, $find)) {
                $skipped[] = 'Một đoạn cần thay không còn khớp với bản nháp nên bỏ qua.';

                continue;
            }

            $edits[] = ['find' => $find, 'replace' => $replace];
        }

        return ['edits' => $edits, 'skipped' => $skipped];
    }

    /**
     * Ánh xạ số thứ tự hiển thị tới mục thật trong payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, item: array<string, mixed>, path: array<int|string>}>
     */
    protected function itemMap(array $payload, ArtifactType $type): array
    {
        $map = [];

        foreach ($this->flatItems($payload, $type) as $number => $entry) {
            if ($type === ArtifactType::Exam) {
                [$sectionIndex, $questionIndex] = $entry['path'];
                $item = (array) ($payload['sections'][$sectionIndex]['questions'][$questionIndex] ?? []);
            } else {
                [$key, $index] = $entry['path'];
                $item = (array) ($payload[$key][$index] ?? []);
            }

            $map[$number] = [
                'label' => $entry['label'],
                'item' => $item,
                'path' => $entry['path'],
            ];
        }

        return $map;
    }

    /**
     * Ghi mục mới vào đúng vị trí trong payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int|string>  $path
     * @param  array<string, mixed>  $new
     */
    protected function writeItem(array &$payload, ArtifactType $type, array $entry, array $new): void
    {
        $path = $entry['path'];

        if ($type === ArtifactType::Exam) {
            [$sectionIndex, $questionIndex] = $path;
            $old = (array) ($payload['sections'][$sectionIndex]['questions'][$questionIndex] ?? []);
            $payload['sections'][$sectionIndex]['questions'][$questionIndex] = $new + $old;
        } else {
            [$key, $index] = $path;
            $old = (array) ($payload[$key][$index] ?? []);
            $payload[$key][$index] = $new + $old;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hasContent(array $item, ArtifactType $type): bool
    {
        if ($type === ArtifactType::Flashcards) {
            return trim((string) ($item['front'] ?? '')) !== '' || trim((string) ($item['back'] ?? '')) !== '';
        }

        if ($type === ArtifactType::MindMap) {
            return trim((string) ($item['label'] ?? $item['title'] ?? '')) !== '';
        }

        return trim((string) ($item['content'] ?? '')) !== '';
    }

    protected function replaceFirst(string $text, string $find, string $replace): string
    {
        $position = mb_strpos($text, $find);

        if ($position === false) {
            return $text;
        }

        return mb_substr($text, 0, $position).$replace.mb_substr($text, $position + mb_strlen($find));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $text): array
    {
        $text = trim($text);
        $text = (string) preg_replace('/^```(?:json)?\s*/u', '', $text);
        $text = (string) preg_replace('/\s*```$/u', '', $text);

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI không trả về đề xuất sửa hợp lệ. Hãy thử diễn đạt yêu cầu rõ hơn.');
        }

        return $decoded;
    }

    protected static function rejectsJsonMode(AiException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, '(400)')
            && (str_contains($message, 'response_format')
                || str_contains($message, 'json_object')
                || str_contains($message, 'json mode'));
    }
}
