<?php

namespace App\Jobs;

use App\Enums\ArtifactType;
use App\Models\NotebookArtifact;
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
 * Chạy nền để giáo viên đóng tab hay chuyển sang màn khác vẫn hoàn tất; kết quả
 * được ghi thẳng vào database nên lần mở sau thấy ngay.
 */
class GenerateArtifact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

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
            $this->fail($artifact, $exception->getMessage());

            return;
        }

        $payload = is_array($data['payload']) ? $data['payload'] : [];
        $payload['_generation'] = $params;
        unset($payload['_error']);

        $artifact->update([
            'title' => $data['title'],
            'payload' => $payload,
            'text_content' => $data['text'],
            'status' => 'draft',
        ]);
    }

    private function fail(NotebookArtifact $artifact, string $reason): void
    {
        $payload = $artifact->payload ?? [];
        $payload['_error'] = Str::limit($reason, 500, '');

        $artifact->update([
            'status' => 'failed',
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

    /**
     * Chạy ngay trong tiến trình hiện tại, dùng cho bản dự phòng khi máy chủ chưa bật worker.
     */
    public static function runInline(int $artifactId): void
    {
        (new self($artifactId))->handle(app(ArtifactGenerator::class));
    }
}
