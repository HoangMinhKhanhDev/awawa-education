<?php

namespace App\Services\Notebook;

use App\Enums\AiPurpose;
use App\Services\Ai\AiManager;

/**
 * Tìm nguồn web: Tavily search + 1 lượt AI nhanh để đánh dấu nguồn chất lượng.
 */
class WebSourceFinder
{
    public function __construct(
        protected TavilyClient $tavily,
        protected AiManager $ai,
    ) {}

    public function configured(): bool
    {
        return $this->tavily->configured();
    }

    /**
     * @return array<int, array{title: string, url: string, content: string, score: float, keep: bool, reason: string}>
     */
    public function find(string $topic, ?int $subjectId = null, ?int $userId = null): array
    {
        $results = $this->tavily->search($topic, (int) config('awawa.notebook.tavily.max_results', 8));

        if ($results === []) {
            return [];
        }

        $verdicts = $this->pickQuality($results, $topic, $subjectId, $userId);

        return array_map(function (array $result, int $index) use ($verdicts): array {
            $verdict = $verdicts[$index + 1] ?? ['keep' => true, 'reason' => ''];

            return $result + ['keep' => (bool) $verdict['keep'], 'reason' => (string) $verdict['reason']];
        }, $results, array_keys($results));
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array{keep: bool, reason: string}>
     */
    protected function pickQuality(array $results, string $topic, ?int $subjectId, ?int $userId): array
    {
        $list = '';

        foreach ($results as $index => $result) {
            $list .= '['.($index + 1).'] '.$result['title'].' — '.mb_substr($result['content'], 0, 300)."\n";
        }

        try {
            $aiResult = $this->ai->chat([
                ['role' => 'system', 'content' => 'Bạn là trợ lý chọn nguồn học thuật đáng tin cho giáo viên. Chỉ trả về JSON hợp lệ.'],
                ['role' => 'user', 'content' => "Chủ đề: {$topic}\n\nDanh sách nguồn:\n{$list}\nHãy trả về mảng JSON [{\"index\":1,\"keep\":true,\"reason\":\"lý do ngắn\"}] cho TẤT CẢ mục; keep=true nếu nguồn đúng chủ đề và đáng tin."],
            ], [
                'purpose' => AiPurpose::WebSourcePicker,
                'subject_id' => $subjectId,
                'user_id' => $userId,
                'temperature' => 0.2,
                'max_tokens' => 800,
            ]);
        } catch (\Throwable) {
            return [];
        }

        $decoded = $this->decodeJsonArray($aiResult->text);

        $verdicts = [];

        foreach ($decoded as $item) {
            if (! isset($item['index'])) {
                continue;
            }

            $verdicts[(int) $item['index']] = [
                'keep' => (bool) ($item['keep'] ?? true),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }

        return $verdicts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function decodeJsonArray(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean) ?? $clean;
        $clean = trim($clean);

        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
