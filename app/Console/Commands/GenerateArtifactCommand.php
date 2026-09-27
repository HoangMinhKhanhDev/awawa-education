<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArtifact;
use App\Models\NotebookArtifact;
use App\Services\Notebook\ArtifactGenerator;
use Illuminate\Console\Command;

/**
 * Soạn một artefact trong tiến trình CLI riêng.
 *
 * Lệnh này do `App\Support\BackgroundProcess` mở ra từ web request khi hosting cho
 * phép `proc_open`, không bao giờ gọi trực tiếp. Nếu hosting chặn, `Studio` dùng
 * tầng `defer` hoặc hàng chờ cron thay cho lệnh này.
 */
class GenerateArtifactCommand extends Command
{
    protected $signature = 'awawa:generate-artifact {artifactId}';

    protected $description = 'Soạn nội dung AI cho một artefact ở tiến trình nền';

    public function handle(): int
    {
        $this->withoutExecutionLimit();

        $artifact = NotebookArtifact::query()->with('notebook.subject')->find((int) $this->argument('artifactId'));

        if ($artifact === null) {
            $this->warn('Không tìm thấy artefact.');

            return self::FAILURE;
        }

        if ($artifact->isPublished() || $artifact->isFailed()) {
            $this->line('Artefact đã ở trạng thái cuối, bỏ qua.');

            return self::SUCCESS;
        }

        $this->components->info('Đang soạn "'.$artifact->title.'"…');

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        if ($artifact->isFailed()) {
            $this->components->error($artifact->failedReason() ?? 'Không soạn được nội dung.');

            return self::FAILURE;
        }

        $this->components->info('Đã soạn xong "'.$artifact->title.'".');

        return self::SUCCESS;
    }

    /**
     * Bỏ giới hạn thời gian chạy và nới bộ nhớ cho tiến trình dài.
     */
    private function withoutExecutionLimit(): void
    {
        @set_time_limit(0);

        @ini_set('memory_limit', (string) config('awawa.notebook.generation_memory', '1024M'));
    }
}
