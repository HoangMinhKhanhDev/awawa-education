<?php

namespace App\Models;

use App\Enums\AssignableType;
use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Một lần giao nội dung cho đội tuyển.
 *
 * Thu hồi chỉ ghi `recalled_at` chứ không xoá, nên lịch sử giao vẫn còn và bài làm
 * của học sinh không bị mất. Giao lại sau khi thu hồi tạo dòng mới.
 *
 * @property-read Model|null $assignable
 * @property-read int $assigned_by
 */
class Assignment extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'assignable_type',
        'assignable_id',
        'assigned_by',
        'assigned_at',
        'recalled_at',
        'recalled_by',
        'recall_reason',
        'due_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'recalled_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AssignmentReceipt::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function recaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }

    public function type(): ?AssignableType
    {
        $assignable = $this->assignable;

        return $assignable === null ? null : AssignableType::fromModel($assignable);
    }

    public function isRecalled(): bool
    {
        return $this->recalled_at !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->isRecalled();
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && $this->isOpen();
    }

    public function daysLeft(): ?int
    {
        return $this->due_at === null ? null : (int) now()->diffInDays($this->due_at, false);
    }

    /**
     * Số học sinh đã nhận / đã mở / đã xong.
     *
     * @return array{delivered: int, opened: int, completed: int, total: int}
     */
    public function progress(): array
    {
        return [
            'delivered' => $this->receipts()->whereNotNull('delivered_at')->count(),
            'opened' => $this->receipts()->whereNotNull('opened_at')->count(),
            'completed' => $this->receipts()->whereNotNull('completed_at')->count(),
            'total' => $this->receipts()->count(),
        ];
    }

    /**
     * Tỉ lệ đã xong dạng 0-100, dùng cho thanh tiến trình.
     */
    public function completionPercent(): int
    {
        $progress = $this->progress();

        if ($progress['total'] === 0) {
            return 0;
        }

        return (int) round($progress['completed'] / $progress['total'] * 100);
    }

    public function receiptFor(User $user): ?AssignmentReceipt
    {
        // Ưu tiên relation đã nạp sẵn. Nếu gọi receipts() thì mỗi lần lại đấu
        // một câu query, và những chỗ duyệt danh sách trong vòng lặp sẽ thành N+1.
        if ($this->relationLoaded('receipts')) {
            return $this->getRelation('receipts')
                ->first(fn (AssignmentReceipt $receipt): bool => $receipt->user_id === $user->id);
        }

        return $this->receipts()->where('user_id', $user->id)->first();
    }

    /**
     * Đang giao: chưa thu hồi.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('recalled_at');
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('assigned_at');
    }
}
