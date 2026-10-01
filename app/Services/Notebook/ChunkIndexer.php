<?php

namespace App\Services\Notebook;

use App\Models\NotebookChunk;
use App\Models\NotebookSource;
use Illuminate\Support\Facades\DB;

/**
 * Nạp chỉ mục ngược cho chunk để truy hồi không phải đọc toàn bộ nội dung.
 *
 * Mỗi chunk được tách thành (từ => tần suất) một lần duy nhất lúc nạp nguồn,
 * rồi `PromptComposer` chỉ truy vấn trên chỉ mục nhỏ này thay vì hydrate hàng
 * triệu ký tự `content` mỗi lần gọi AI. Từ trong tiêu đề nguồn được nạp riêng
 * với cờ `in_title` để tính điểm cao hơn từ trong nội dung.
 *
 * Nạp lại cùng nguồn thì xoá chỉ mục cũ trước nên không bao giờ trùng. Hai
 * request cùng nạp thì `insertOrIgnore` nhờ unique (chunk_id, term, in_title).
 */
class ChunkIndexer
{
    /**
     * Nạp chỉ mục cho toàn bộ chunk của một nguồn.
     */
    public function indexSource(NotebookSource $source): void
    {
        $chunks = NotebookChunk::query()
            ->where('source_id', $source->id)
            ->orderBy('position')
            ->get(['id', 'notebook_id', 'source_id', 'position', 'content']);

        if ($chunks->isEmpty()) {
            return;
        }

        DB::table('notebook_chunk_terms')->where('source_id', $source->id)->delete();

        $titleFrequencies = VietnameseTerms::frequencies((string) $source->title);
        $rows = [];
        $counts = [];

        foreach ($chunks as $chunk) {
            $frequencies = VietnameseTerms::frequencies((string) $chunk->content);
            $counts[$chunk->id] = array_sum($frequencies);

            foreach ($frequencies as $term => $tf) {
                $rows[] = [
                    'chunk_id' => $chunk->id,
                    'notebook_id' => $chunk->notebook_id,
                    'source_id' => $chunk->source_id,
                    'term' => $term,
                    'tf' => min($tf, 32767),
                    'in_title' => false,
                ];
            }

            foreach ($titleFrequencies as $term => $tf) {
                $rows[] = [
                    'chunk_id' => $chunk->id,
                    'notebook_id' => $chunk->notebook_id,
                    'source_id' => $chunk->source_id,
                    'term' => $term,
                    'tf' => min($tf, 32767),
                    'in_title' => true,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $batch) {
            DB::table('notebook_chunk_terms')->insertOrIgnore($batch);
        }

        $this->storeTermCounts($counts);
    }

    /**
     * Nguồn nào trong danh sách chưa được nạp chỉ mục.
     *
     * @param  array<int, int>  $sourceIds
     * @return array<int, int>
     */
    public function missingSourceIds(array $sourceIds): array
    {
        if ($sourceIds === []) {
            return [];
        }

        $indexed = DB::table('notebook_chunk_terms')
            ->whereIn('source_id', $sourceIds)
            ->distinct()
            ->pluck('source_id')
            ->all();

        $hasChunks = NotebookChunk::query()
            ->whereIn('source_id', $sourceIds)
            ->distinct()
            ->pluck('source_id')
            ->all();

        return array_values(array_diff(array_intersect($sourceIds, $hasChunks), $indexed));
    }

    /**
     * Ghi độ dài chunk bằng một UPDATE duy nhất, thay vì N câu lệnh.
     *
     * @param  array<int, int>  $counts  chunk_id => tổng số từ
     */
    protected function storeTermCounts(array $counts): void
    {
        if ($counts === []) {
            return;
        }

        $cases = [];
        $ids = [];

        foreach ($counts as $chunkId => $termCount) {
            $chunkId = (int) $chunkId;
            $cases[] = "WHEN {$chunkId} THEN ".max(0, (int) $termCount);
            $ids[] = $chunkId;
        }

        DB::update('UPDATE notebook_chunks SET term_count = CASE id '.implode(' ', $cases).' END WHERE id IN ('.implode(',', $ids).')');
    }
}
