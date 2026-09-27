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

    protected $description = 'Soạn các nội dung AI đang chờ trong database';

    public function handle(ArtifactGenerator $generator): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));

        $batch = max(1, (int) config('awawa.notebook.pending_batch', 5));
        $deadline = microtime(true) + max(30, (int) config('awawa.notebook.pending_time_budget', 240));

        $done = 0;
        $failed = 0;

        // Nhận nhiều nội dung mỗi lượt thay vì một: một lần soạn mất hàng chục giây
        // nên nếu chỉ lấy một, giáo viên bấm "Tạo" ba lần là phải chờ ba phút nữa.
        // Vẫn dừng theo ngân sách thời gian để không tràn giờ của cron.
        while ($done + $failed < $batch && microtime(true) < $deadline) {
            $artifact = $this->claimNext();

            if ($artifact === null) {
                break;
            }

            if ($this->generateOne($artifact, $generator)) {
                $done++;
            } else {
                $failed++;
            }
        }

        if ($done === 0 && $failed === 0) {
            return self::SUCCESS;
        }

        $this->components->info('Đã soạn '.$done.' nội dung'.($failed > 0 ? ", {$failed} nội dung lỗi." : '.'));

        return $done === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Nhận một nội dung đang chờ.
     *
     * Ưu tiên nội dung thật sự nằm trong hàng chờ (`scheduler`). Nội dung đã được
     * tiến trình nhận nhưng tiến trình chết được nhặt lại khi đã quá ngưỡng treo, để
     * không giành việc của một tiến trình vẫn đang chạy.
     */
    protected function claimNext(): ?NotebookArtifact
    {
        $queued = NotebookArtifact::query()
            ->where('status', 'generating')
            ->where('payload->_generation_runner', 'scheduler')
            ->oldest('created_at')
            ->oldest('id')
            ->first();

        $artifact = $queued ?? NotebookArtifact::query()
            ->where('status', 'generating')
            ->whereIn('payload->_generation_runner', NotebookArtifact::RUNNERS_ACTIVE)
            ->where('updated_at', '<=', now()->subMinutes($this->staleMinutes()))
            ->oldest('created_at')
            ->oldest('id')
            ->first();

        if ($artifact === null) {
            return null;
        }

        $expected = (string) ($artifact->payload['_generation_runner'] ?? '');
        $payload = $artifact->payload ?? [];

        // Claim nguyên tử: web request và cron có thể cùng nhìn thấy một nội dung,
        // chỉ bên nào ghi được thì bên đó chạy. Giữ nguyên từ vựng runner để
        // `Studio::poll` vẫn dọn được nếu tiến trình này chết giữa chừng.
        $payload['_generation_runner'] = 'scheduler_running';

        $claimed = NotebookArtifact::query()
            ->whereKey($artifact->id)
            ->where('status', 'generating')
            ->where('payload->_generation_runner', $expected)
            ->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return $this->claimNext();
        }

        return $artifact->refresh();
    }

    protected function generateOne(NotebookArtifact $artifact, ArtifactGenerator $generator): bool
    {
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

            return false;
        }

        $this->components->info('Đã soạn xong "'.$artifact->title.'".');

        return true;
    }

    protected function staleMinutes(): int
    {
        return max(1, (int) config('awawa.notebook.stale_minutes', 30));
    }
}
