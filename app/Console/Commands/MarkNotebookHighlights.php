<?php

namespace App\Console\Commands;

use App\Models\NotebookChunk;
use App\Models\NotebookSource;
use App\Services\Notebook\HighlightPicker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notebook:mark-highlights')]
#[Description('Đánh dấu lại các câu trích nổi bật cho nguồn đã tạo trước khi có cột is_highlight')]
class MarkNotebookHighlights extends Command
{
    public function handle(HighlightPicker $picker): int
    {
        $sources = 0;
        $marked = 0;

        NotebookSource::query()
            ->where('status', 'ready')
            ->select('id')
            ->chunkById(50, function ($sourceList) use ($picker, &$sources, &$marked): void {
                $chunksBySource = NotebookChunk::query()
                    ->whereIn('source_id', $sourceList->pluck('id'))
                    ->orderBy('source_id')
                    ->orderBy('position')
                    ->get(['id', 'source_id', 'position', 'content'])
                    ->groupBy('source_id');

                foreach ($chunksBySource as $chunks) {
                    $highlightIds = $picker->highlightedChunkIds($chunks->values()->map(fn (NotebookChunk $chunk): array => [
                        'id' => $chunk->id,
                        'position' => $chunk->position,
                        'content' => (string) $chunk->content,
                    ])->all());

                    NotebookChunk::query()->whereIn('id', $chunks->pluck('id'))->update(['is_highlight' => false]);

                    if ($highlightIds !== []) {
                        NotebookChunk::query()->whereIn('id', $highlightIds)->update(['is_highlight' => true]);
                    }

                    $sources++;
                    $marked += count($highlightIds);
                }
            });

        $this->info("Đã đánh dấu {$marked} đoạn chứa câu trích nổi bật trong {$sources} nguồn.");

        return self::SUCCESS;
    }
}
