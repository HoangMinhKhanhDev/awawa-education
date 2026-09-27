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
     * Các runner đã nhận việc: tiến trình gọi AI thật sự, nên `updated_at` không
     * phản ánh tiến độ. Nếu tiến trình chết thì nội dung mồ côi và cron nhặt lại.
     *
     * @var list<string>
     */
    public const RUNNERS_ACTIVE = ['process', 'respond', 'inline', 'scheduler_running'];

    /**
     * Đóng dấu một lần soạn bị treo. Chỗ duy nhất được phép ghi trạng thái treo,
     * để `Studio::poll` và cron không tự viết payload theo hai kiểu khác nhau.
     */
    public function markStalled(string $reason): void
    {
        $payload = $this->payload ?? [];
        $payload['_error'] = Str::limit($reason, 500, '');
        unset($payload['_generation_runner']);

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'payload' => $payload,
        ])->save();
    }
}
