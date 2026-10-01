<?php

namespace App\Jobs;

use App\Enums\ArtifactType;
use App\Models\NotebookArtifact;
use App\Services\Ai\AiException;
use App\Services\Notebook\ArtifactGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Soạn nội dung AI cho một artefact đã tạo sẵn ở trạng thái "generating".
 *
 * Chạy ngoài web request để giáo viên đóng tab hay chuyển sang màn khác vẫn hoàn
 * tất. Ưu tiên là hàng chờ do cron dọn mỗi phút, vì đó là tầng duy nhất thử lại
 * được lỗi hạ tầng thật; không có hàng chờ thì `Studio` chạy `handle()` trực
 * tiếp qua tiến trình con, `defer` hoặc ngay trong request.
 *
 * `handle()` vì thế phải đúng ở cả hai chế độ: được xếp hàng thì worker đếm
 * lượt thử theo `$tries` + `backoff()` rồi gọi `failed()` khi hết lượt; gọi trực
 * tiếp thì job tự thử lại trong phạm vi hẹn vì không có ai đếm hộ.
 */
class GenerateArtifact implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Kết nối và tên hàng đợi riêng: `retry_after` của hàng đợi này phải lớn hơn
     * `$timeout` của job, không thể dùng chung với job ngắn của hàng `default`.
     */
    public const CONNECTION = 'ai';

    public const QUEUE = 'ai';

    /**
     * Số lần thử tối đa cho một lần soạn, tính cả lần đầu. Chỉ lỗi hạ tầng mới
     * được thử lại, xem `isRetryable()`.
     */
    public int $tries = 3;

    /**
     * Phải lớn hơn `awawa.notebook.artifact_timeout` (300s) và nhỏ hơn
     * `queue.connections.ai.retry_after`, nếu không queue sẽ bỏ job khi AI còn
     * đang trả lời.
     */
    public int $timeout = 330;

    /**
     * Khoá chống soạn trùng cho cùng một artefact. Phải phủ trọn vòng thử lại tệ
     * nhất (`$tries` lần `$timeout` cộng `$backoff`) để lần xếp hàng sau không
     * chen vào giữa lúc lần trước còn đang chạy.
     */
    public int $uniqueFor = 1800;

    /**
     * Số lần thử khi `handle()` được gọi trực tiếp: thêm một lần thử nữa là đủ để
     * thoát lỗi mạng chớp chút, phần còn lại thuộc về hàng chờ.
     */
    private const DIRECT_ATTEMPTS = 2;

    /**
     * Bao lâu chờ giữa hai lần thử khi gọi trực tiếp, không có worker lo phần
     * backoff. Giữ nhỏ vì lần gọi đó còn phải trả về cho trình duyệt.
     */
    private const DIRECT_RETRY_WAIT_SECONDS = 2;

    public function __construct(public int $artifactId, public bool $rescued = false)
    {
        $this->onConnection(self::CONNECTION);
        $this->onQueue(self::QUEUE);
    }

    /**
     * Một artefact chỉ được soạn bởi một job tại một thời điểm.
     */
    public function uniqueId(): string
    {
        return (string) $this->artifactId;
    }

    /**
     * Số giây chờ trước lần thử thứ hai, rồi lần thứ ba. Cron trên Hostinger chạy
     * mỗi phút nên giữ ngắn, nhưng đủ để lỗi chớp qua đã hết.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 45];
    }

    public function handle(ArtifactGenerator $generator): void
    {
        $artifact = NotebookArtifact::query()->with('notebook.subject')->find($this->artifactId);

        // Không còn ở trạng thái "generating" nghĩa là đã có kết quả, hoặc một
        // lần soạn khác đã xử lý xong: im lặng bỏ qua, không gọi AI lần nữa.
        if ($artifact === null || ! $artifact->isGenerating()) {
            return;
        }

        $params = $artifact->payload['_generation'] ?? null;
        $type = ArtifactType::tryFrom($artifact->type);

        if (! is_array($params) || $type === null) {
            $this->markArtifactFailed('Bản nháp thiếu thông tin tạo nội dung.');

            return;
        }

        if ($this->supersededByRescue($artifact->payload ?? [])) {
            return;
        }

        $this->markClaimed($artifact);

        $data = null;
        $attempt = 0;

        while ($data === null) {
            $attempt++;

            try {
                $data = $generator->generate(
                    $artifact->notebook,
                    $type,
                    $params,
                    $artifact->subject_id,
                    $artifact->user_id,
                    function (int $done, int $total): void {
                        $payload = NotebookArtifact::query()->find($this->artifactId)?->payload ?? [];
                        $payload['_progress'] = ['done' => $done, 'total' => $total];

                        NotebookArtifact::query()->whereKey($this->artifactId)->update(['payload' => $payload]);
                    },
                );
            } catch (\Throwable $exception) {
                if (! $this->isRetryable($exception, $attempt)) {
                    $this->stopGeneration($exception);

                    return;
                }

                // Đang nằm trong worker: ném lại để Laravel áp dụng `$tries` với
                // `backoff()` rồi gọi `failed()` khi hết lượt. Gọi trực tiếp thì
                // không có ai đếm lượt, phải tự chờ rồi thử tiếp.
                if ($this->job !== null) {
                    throw $exception;
                }

                sleep(self::DIRECT_RETRY_WAIT_SECONDS);
            }
        }

        $payload = is_array($data['payload']) ? $data['payload'] : [];
        $payload['_generation'] = $params;
        unset($payload['_error'], $payload['_generation_runner'], $payload['_progress'], $payload['_claimed_at'], $payload['_rescued_at']);

        $artifact->update([
            'title' => $data['title'],
            'payload' => $payload,
            'text_content' => $data['text'],
            'status' => 'draft',
        ]);
    }

    /**
     * Dừng hẳn một lần soạn: đóng dấu artefact hỏng ngay, không xếp lại.
     *
     * Job được xếp hàng thì đi qua `fail()` của Laravel để còn ghi vào
     * `failed_jobs`; gọi trực tiếp thì tự gọi `failed()`.
     */
    protected function stopGeneration(\Throwable $exception): void
    {
        if ($this->job !== null) {
            $this->fail($exception);

            return;
        }

        $this->failed($exception);
    }

    /**
     * Lỗi hạ tầng (rớt mạng, hết thời gian chờ, máy chủ lỗi, bị giới hạn lượt
     * gọi) thì thử lại được. Lỗi cấu hình (thiếu API key, provider bị tắt, sai
     * tên model, sai thông tin đăng nhập, 4xx) thì không: thử lại chỉ kéo dài
     * thời gian chờ của giáo viên và đốt thêm lượt gọi AI mà không bao giờ ra
     * kết quả.
     */
    protected function isRetryable(\Throwable $exception, int $attempt): bool
    {
        if ($attempt >= $this->maxAttempts()) {
            return false;
        }

        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof AiException) {
            return false;
        }

        if ($exception->isRateLimited()) {
            return true;
        }

        $message = $exception->getMessage();

        // "Không kết nối được tới nhà cung cấp AI: ..." cho lỗi mạng và timeout;
        // "Lỗi nhà cung cấp AI (5xx): ..." cho máy chủ nhà cung cấp lỗi.
        return str_starts_with($message, 'Không kết nối được')
            || preg_match('/AI \(5\d\d\)/', $message) === 1;
    }

    /**
     * Còn bao nhiêu lần thử được phép. Job được xếp hàng thì worker đếm theo
     * `$tries`; gọi trực tiếp thì tự giới hạn ở mức vừa phải.
     */
    protected function maxAttempts(): int
    {
        return $this->job === null ? self::DIRECT_ATTEMPTS : $this->tries;
    }

    /**
     * `Studio::poll` đã tự soạn nốt việc này vì thấy hàng đời không có worker
     * nào chạy: job còn nằm trong hàng đời thì thừa, bỏ qua để không soạn trùng.
     * Job do chính `Studio` gọi để cứu thì mang cờ `$rescued` nên vẫn chạy.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function supersededByRescue(array $payload): bool
    {
        return ! $this->rescued && ! empty($payload['_rescued_at']);
    }

    /**
     * Đánh dấu đã có người nhận việc này.
     *
     * `Studio::poll` dựa vào dấu hiệu này để phân biệt "đang nằm trong hàng
     * đợi chờ worker" với "worker đã nhận và đang chạy". Không có nó thì một
     * việc soạn dài có thể bị cứu nhầm giữa chừng.
     */
    protected function markClaimed(NotebookArtifact $artifact): void
    {
        $payload = $artifact->payload ?? [];

        if (! empty($payload['_claimed_at'])) {
            return;
        }

        $payload['_claimed_at'] = now()->timestamp;
        $artifact->update(['payload' => $payload]);
    }

    /**
     * Đóng dấu một lần soạn thất bại, giữ nguyên thông tin tham số để giáo viên
     * bấm "Tạo lại" là chạy lại đúng cấu hình cũ.
     */
    protected function markArtifactFailed(string $reason): void
    {
        $artifact = NotebookArtifact::query()->find($this->artifactId);

        if ($artifact === null || ! $artifact->isGenerating()) {
            return;
        }

        $payload = $artifact->payload ?? [];
        $payload['_error'] = Str::limit($reason, 500, '');
        unset($payload['_generation_runner'], $payload['_progress']);

        $artifact->update([
            'status' => NotebookArtifact::STATUS_FAILED,
            'payload' => $payload,
        ]);
    }

    /**
     * Điểm dừng cuối cùng của cả hai chế độ: worker gọi khi hết lượt thử, gọi
     * trực tiếp thì `handle()` tự gọi khi lỗi không thể thử lại.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->markArtifactFailed($exception?->getMessage() ?: 'Không hoàn tất được yêu cầu.');
    }
}
