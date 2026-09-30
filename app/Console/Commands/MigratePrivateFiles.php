<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\NotebookSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('documents:migrate-to-private {--delete-source : Xoa file public goc sau khi copy thanh cong}')]
#[Description('Copy file documents/notebook tu disk public sang disk local (private), verify roi moi xoa. Idempotent, chay lai duoc.')]
class MigratePrivateFiles extends Command
{
    public function handle(): int
    {
        $deleteSource = (bool) $this->option('delete-source');
        $copied = 0;
        $skipped = 0;
        $missing = 0;

        $paths = Document::query()->pluck('file_path')->filter()->unique()->values()
            ->merge(NotebookSource::query()->whereNotNull('file_path')->pluck('file_path')->filter()->unique()->values())
            ->unique()->values();

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path)) {
                $skipped++;

                if ($deleteSource && Storage::disk('public')->exists($path)) {
                    $localSize = Storage::disk('local')->size($path);
                    $publicSize = Storage::disk('public')->size($path);

                    if ($localSize === $publicSize) {
                        Storage::disk('public')->delete($path);
                    }
                }

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $missing++;
                $this->warn("Thieu file goc: {$path}");

                continue;
            }

            $contents = Storage::disk('public')->get($path);

            if (! is_string($contents)) {
                $missing++;
                $this->warn("Khong doc duoc: {$path}");

                continue;
            }

            Storage::disk('local')->put($path, $contents);

            $localSize = Storage::disk('local')->size($path);
            $publicSize = Storage::disk('public')->size($path);

            if ($localSize !== $publicSize) {
                Storage::disk('local')->delete($path);
                $this->error("Size mismatch, bo qua: {$path}");

                continue;
            }

            $copied++;

            if ($deleteSource) {
                Storage::disk('public')->delete($path);
            }
        }

        $this->info("Xong: copied={$copied} skipped={$skipped} missing={$missing}.");

        return self::SUCCESS;
    }
}
