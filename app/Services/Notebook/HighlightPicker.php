<?php

namespace App\Services\Notebook;

use Illuminate\Support\Str;

/**
 * Rút các câu trích đáng chú ý của một nguồn để giáo viên đọc nhanh trước khi đọc toàn văn.
 * Chạy hoàn toàn bằng quy tắc, không gọi AI, nên không tốn lượt gọi nhà cung cấp.
 */
class HighlightPicker
{
    public const MAX_PASSAGES = 8;

    public const MAX_PER_CHUNK = 2;

    public const MIN_PASSAGE_LENGTH = 30;

    public const MAX_PASSAGE_LENGTH = 320;

    /**
     * @var array<int, string>
     */
    protected const KEYWORDS = [
        'định nghĩa', 'định lý', 'định luật', 'công thức', 'kết luận', 'nhận xét',
        'ví dụ', 'lưu ý', 'ghi chú', 'chú ý', 'tính chất', 'điều kiện', 'hệ quả',
        'bài toán', 'tóm tắt', 'mục tiêu', 'phân loại', 'đặc điểm', 'nguyên nhân',
        'gồm', 'là gì', 'được định nghĩa', 'áp dụng',
    ];

    /**
     * @param  array<int, array{id: int, position: int, content: string}>  $chunks
     * @return array<int, array{chunk_id: int, position: int, text: string}>
     */
    public function passages(array $chunks, int $limit = self::MAX_PASSAGES): array
    {
        $candidates = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            $chunkId = (int) $chunk['id'];
            $position = (int) $chunk['position'];
            $isEdge = $position === 0 || $position === (int) (max(array_column($chunks, 'position')));

            foreach ($this->splitIntoPassages((string) $chunk['content']) as $text) {
                $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? $text);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $candidates[] = [
                    'chunk_id' => $chunkId,
                    'position' => $position,
                    'text' => $text,
                    'score' => $this->score($text, $isEdge),
                ];
            }
        }

        usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $perChunk = [];
        $picked = [];

        foreach ($candidates as $candidate) {
            $used = $perChunk[$candidate['chunk_id']] ?? 0;

            if ($used >= self::MAX_PER_CHUNK) {
                continue;
            }

            $perChunk[$candidate['chunk_id']] = $used + 1;
            $picked[] = $candidate;

            if (count($picked) >= $limit) {
                break;
            }
        }

        usort($picked, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_map(fn (array $passage): array => [
            'chunk_id' => $passage['chunk_id'],
            'position' => $passage['position'],
            'text' => $passage['text'],
        ], $picked);
    }

    /**
     * @param  array<int, array{id: int, position: int, content: string}>  $chunks
     * @return array<int, int> các id đoạn có câu trích được chọn
     */
    public function highlightedChunkIds(array $chunks, int $limit = self::MAX_PASSAGES): array
    {
        return array_values(array_unique(array_column($this->passages($chunks, $limit), 'chunk_id')));
    }

    /**
     * @return array<int, string>
     */
    protected function splitIntoPassages(string $content): array
    {
        $passages = [];

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) <= self::MAX_PASSAGE_LENGTH) {
                $passages[] = $line;

                continue;
            }

            foreach (preg_split('/(?<=[.!?…])\s+/u', $line) ?: [] as $sentence) {
                $sentence = trim($sentence);

                if ($sentence !== '') {
                    $passages[] = $sentence;
                }
            }
        }

        return array_values(array_filter(
            $passages,
            fn (string $passage): bool => mb_strlen($passage) >= self::MIN_PASSAGE_LENGTH,
        ));
    }

    protected function score(string $text, bool $isEdgeChunk): float
    {
        $normalized = mb_strtolower($text);
        $score = 0.0;

        foreach (self::KEYWORDS as $keyword) {
            if (Str::contains($normalized, $keyword)) {
                $score += 4;

                break;
            }
        }

        if (preg_match('/[0-9]\s*[=+\-−×÷≤≥<>]\s*[0-9a-z]/u', $text) === 1) {
            $score += 2;
        }

        $length = mb_strlen($text);

        if ($length >= 60 && $length <= 240) {
            $score += 1.5;
        } elseif ($length > 300) {
            $score -= 1;
        }

        if ($isEdgeChunk) {
            $score += 0.5;
        }

        return $score;
    }
}
