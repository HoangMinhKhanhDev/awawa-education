<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trạng thái của một học sinh với một lần giao.
 *
 * `delivered_at` lúc giao, `opened_at` khi học sinh mở ra, `completed_at` khi
 * làm xong. Với đề thi, `completed_at` được suy ra từ `ExamAttempt` nên không
 * nhất thiết phải ghi.
 */
class AssignmentReceipt extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'subject_id',
        'user_id',
        'delivered_at',
        'opened_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function markDelivered(): void
    {
        if ($this->delivered_at === null) {
            $this->forceFill(['delivered_at' => now()])->save();
        }
    }

    public function markOpened(): void
    {
        $changes = $this->opened_at === null ? ['opened_at' => now()] : [];

        if ($this->completed_at !== null) {
            $changes['completed_at'] = null;
        }

        if ($changes !== []) {
            $this->forceFill($changes)->save();
        }
    }

    /**
     * Bấm lại thì bỏ dấu xong để học sinh phải làm lại nếu giao lại.
     */
    public function markCompleted(): void
    {
        $changes = [];

        if ($this->delivered_at === null) {
            $changes['delivered_at'] = now();
        }

        if ($this->opened_at === null) {
            $changes['opened_at'] = now();
        }

        if ($this->completed_at === null) {
            $changes['completed_at'] = now();
        }

        if ($changes !== []) {
            $this->forceFill($changes)->save();
        }
    }
}
