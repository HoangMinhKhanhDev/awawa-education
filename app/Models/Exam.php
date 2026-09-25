<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'created_by',
        'type',
        'title',
        'description',
        'duration_minutes',
        'total_points',
        'shuffle_questions',
        'shuffle_options',
        'starts_at',
        'ends_at',
        'due_at',
        'status',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'status' => ExamStatus::class,
            'duration_minutes' => 'integer',
            'total_points' => 'decimal:2',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'due_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('order');
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['exam_section_id', 'order', 'points'])
            ->withTimestamps();
    }

    public function isPublished(): bool
    {
        return $this->status === ExamStatus::Published;
    }

    public function isExam(): bool
    {
        return $this->type === ExamType::Exam;
    }

    public function refreshTotalPoints(): void
    {
        $total = $this->examQuestions()
            ->get()
            ->sum(fn (ExamQuestion $examQuestion) => (float) ($examQuestion->points ?? $examQuestion->question?->points ?? 0));

        $this->forceFill(['total_points' => $total])->save();
    }

    public function scopeOfType(Builder $query, ExamType|string $type): Builder
    {
        $value = $type instanceof ExamType ? $type->value : $type;

        return $query->where('type', $value);
    }
}
