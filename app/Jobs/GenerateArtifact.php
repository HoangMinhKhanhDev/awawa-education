<?php

namespace App\Jobs;

use App\Enums\ArtifactType;
use App\Models\NotebookArtifact;
use App\Services\Ai\AiException;
use App\Services\Notebook\ArtifactGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Soạn nội dung AI cho một artefact đã tạo sẵn ở trạng thái "generating".
 *
 * Chạy ngoài web request (tiến trình CLI do `awawa:generate-artifact` gọi) để giáo
 * viên đóng tab hay chuyển sang màn khác vẫn hoàn tất; kết quả được ghi thẳng vào
 * database nên lần mở sau thấy ngay.
 */
class GenerateArtifact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Phải lớn hơn `awawa.notebook.artifact_timeout`, nếu không queue sẽ bỏ job
     * khi AI còn đang trả lời.
     */
    public int $timeout = 330;

    public function __construct(public int $artifactId) {}

    public function handle(ArtifactGenerator $generator): void
    {
        $artifact = NotebookArtifact::query()->with('notebook.subject')->find($this->artifactId);

        if ($artifact === null || ! $artifact->isGenerating()) {
            return;
        }

        $params = $artifact->payload['_generation'] ?? null;
        $type = ArtifactType::tryFrom($artifact->type);

        if (! is_array($params) || $type === null) {
            $this->fail($artifact, 'Bản nháp thiếu thông tin tạo nội dung.');

            return;
        }

        try {
            $data = $generator->generate(
                $artifact->notebook,
                $type,
                $params,
                $artifact->subject_id,
                $artifact->user_id,
            );
        } catch (\Throwable $exception) {
            $this->fail($artifact, $exception->getMessage(), $exception instanceof AiException && $exception->isRateLimited());

            return;
        }

        $payload = is_array($data['payload']) ? $data['payload'] : [];
        $payload['_generation'] = $params;
        unset($payload['_error'], $payload['_error_is_rate_limited'], $payload['_generation_runner']);

        $artifact->update([
            'title' => $data['title'],
            'payload' => $payload,
            'text_content' => $data['text'],
            'status' => 'draft',
        ]);
    }

    /**
     * Ghi lý do thất bại. Cờ hạn mức được lưu riêng để giao diện hiện đúng lời nhắc
     * "cần nhà cung cấp dự phòng" thay vì lỗi kỹ thuật chung chung.
     */
    private function fail(NotebookArtifact $artifact, string $reason, bool $rateLimited = false): void
    {
        $payload = $artifact->payload ?? [];
        $payload['_error'] = Str::limit($reason, 500, '');

        if ($rateLimited) {
            $payload['_error_is_rate_limited'] = true;
        }

        unset($payload['_generation_runner']);

        $artifact->update([
            'status' => NotebookArtifact::STATUS_FAILED,
            'payload' => $payload,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        $artifact = NotebookArtifact::query()->find($this->artifactId);

        if ($artifact === null || ! $artifact->isGenerating()) {
            return;
        }

        $this->fail($artifact, $exception?->getMessage() ?: 'Không hoàn tất được yêu cầu.');
    }
}
