<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class NotebookArtifact extends Model
{
    use HasFactory;

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notebook_id',
        'subject_id',
        'user_id',
        'type',
        'title',
        'payload',
        'text_content',
        'status',
        'ref_type',
        'ref_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isGenerating(): bool
    {
        return $this->status === 'generating';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function failedReason(): ?string
    {
        if (! $this->isFailed()) {
            return null;
        }

        $reason = $this->payload['_error'] ?? null;

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : 'AI không tạo được nội dung.';
    }

    /**
     * Runner đã nhận việc và đang thực sự gọi AI, nên `updated_at` không phản ánh
     * tiến độ: nếu tiến trình chết thì artifact mồ côi và cần được nhặt lại.
     */
    public function hasActiveRunner(): bool
    {
        return in_array($this->runner(), ['process', 'respond', 'inline', 'scheduler_running'], true);
    }

    /**
     * Tất cả runner đã nhận việc, dùng chung cho bộ dọn treo lẫn cron cứu mồ côi.
     *
     * @return list<string>
     */
    public static function activeRunners(): array
    {
        return ['process', 'respond', 'inline', 'scheduler_running'];
    }

    public function runner(): ?string
    {
        $runner = $this->payload['_generation_runner'] ?? null;

        return is_string($runner) && $runner !== '' ? $runner : null;
    }

    /**
     * Đóng dấu một lần soạn bị treo. Dùng chung để `Studio::poll` và cron không
     * tự viết payload theo hai kiểu khác nhau.
     */
    public function markStalled(string $reason): void
    {
        $payload = $this->payload ?? [];
        $payload['_error'] = Str::limit($reason, 500, '');
        unset($payload['_generation_runner'], $payload['_error_is_rate_limited']);

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'payload' => $payload,
        ])->save();
    }
}
