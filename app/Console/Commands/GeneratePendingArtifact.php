<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArtifact;
use App\Models\NotebookArtifact;
use App\Services\Notebook\ArtifactGenerator;
use Illuminate\Console\Command;
use Throwable;

class GeneratePendingArtifact extends Command
{
    protected $signature = 'awawa:generate-pending-artifact';

    protected $description = 'Soạn một nội dung AI đang chờ trong database';

    public function handle(ArtifactGenerator $generator): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));

        $artifact = NotebookArtifact::query()
            ->where('status', 'generating')
            ->where('payload->_generation_runner', 'scheduler')
            ->oldest('created_at')
            ->oldest('id')
            ->first();

        if ($artifact === null) {
            return self::SUCCESS;
        }

        $payload = $artifact->payload ?? [];
        $payload['_generation_runner'] = 'scheduler_running';
        $artifact->update(['payload' => $payload]);

        $this->components->info('Đang soạn "'.$artifact->title.'"…');

        $job = new GenerateArtifact($artifact->id);

        try {
            $job->handle($generator);
        } catch (Throwable $exception) {
            $job->failed($exception);
        }

        $artifact->refresh();

        if ($artifact->isFailed()) {
            $this->components->error($artifact->failedReason() ?? 'Không soạn được nội dung.');

            return self::FAILURE;
        }

        $this->components->info('Đã soạn xong "'.$artifact->title.'".');

        return self::SUCCESS;
    }
}
