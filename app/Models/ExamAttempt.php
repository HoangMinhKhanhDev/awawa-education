<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'exam_id',
        'student_id',
        'status',
        'started_at',
        'expires_at',
        'submitted_at',
        'graded_at',
        'graded_by',
        'score',
        'max_score',
        'auto_score',
        'manual_score',
        'anti_cheat',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'auto_score' => 'decimal:2',
            'manual_score' => 'decimal:2',
            'anti_cheat' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function remainingSeconds(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Tính lại điểm từ các câu trả lời đã chấm.
     */
    public function recomputeScore(): void
    {
        $answers = $this->answers()->with('question')->get();

        $auto = 0.0;
        $manual = 0.0;

        foreach ($answers as $answer) {
            $points = $answer->awarded_points === null ? 0.0 : (float) $answer->awarded_points;

            if ($answer->question?->type === QuestionType::Essay) {
                $manual += $points;
            } else {
                $auto += $points;
            }
        }

        $this->forceFill([
            'auto_score' => $auto,
            'manual_score' => $manual,
            'score' => $auto + $manual,
        ])->save();
    }

    /**
     * Còn câu tự luận nào chưa chấm không.
     */
    public function hasPendingManualGrading(): bool
    {
        return $this->answers()
            ->whereHas('question', fn (Builder $query) => $query->where('type', QuestionType::Essay->value))
            ->whereNull('awarded_points')
            ->exists();
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Graded->value]);
    }
}
