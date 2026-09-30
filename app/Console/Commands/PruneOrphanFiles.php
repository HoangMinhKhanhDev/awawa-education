<?php

namespace App\Console\Commands;

use App\Models\Notebook;
use App\Models\NotebookSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Dọn file trên disk mà không còn bản ghi nào trỏ tới.
 *
 * Shared hosting không có supervisor nên không có tác vụ nền nào tự dọn được:
 * file tải lên tạm của Livewire và tài liệu của notebook đã xoá cứ nằm lại cho
 * tới khi host đầy dung lượng. Lệnh này chạy bằng cron mỗi ngày.
 */
#[Signature('files:prune-orphans {--dry-run : Chỉ báo cáo, không xoá}')]
#[Description('Xoá file tạm Livewire quá hạn và file của notebook đã bị xoá')]
class PruneOrphanFiles extends Command
{
    /** File tải lên tạm của Livewire có thể xoá sau 6 giờ. */
    private const LIVEWIRE_TTL_HOURS = 6;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tmpDeleted = $this->pruneLivewireTmp($dryRun);
        $notebookDeleted = $this->pruneNotebookFiles($dryRun);

        $this->info("Xong: livewire-tmp={$tmpDeleted} notebook={$notebookDeleted}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    private function pruneLivewireTmp(bool $dryRun): int
    {
        $count = 0;
        $disk = Storage::disk('public');
        $cutoff = now()->subHours(self::LIVEWIRE_TTL_HOURS)->getTimestamp();

        foreach ($disk->files('livewire-tmp') as $path) {
            if ($disk->lastModified($path) >= $cutoff) {
                continue;
            }

            if (! $dryRun) {
                $disk->delete($path);
            }

            $count++;
        }

        return $count;
    }

    private function pruneNotebookFiles(bool $dryRun): int
    {
        $count = 0;
        $disk = Storage::disk('public');

        // Chỉ dò các thư mục con của notebook/, mỗi thư mục là một notebook id.
        foreach ($disk->directories('notebook') as $dir) {
            $id = (int) basename($dir);

            if ($id <= 0 || $this->notebookStillExists($id)) {
                continue;
            }

            foreach ($disk->files($dir) as $path) {
                if (! $dryRun) {
                    $disk->delete($path);
                }

                $count++;
            }

            if (! $dryRun) {
                $disk->deleteDirectory($dir);
            }
        }

        return $count;
    }

    private function notebookStillExists(int $id): bool
    {
        if (Notebook::query()->whereKey($id)->exists()) {
            return true;
        }

        // Thư mục có thể còn sót lại từ khi notebook đã xoá nhưng bản ghi nguồn thì
        // chưa: giữ lại cho tới khi không còn nguồn nào thuộc notebook đó.
        return NotebookSource::query()->where('notebook_id', $id)->exists();
    }
}
