<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'created_by',
        'type',
        'content',
        'answer',
        'explanation',
        'difficulty',
        'points',
        'topic',
        'tags',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'points' => 'decimal:2',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function correctOptions(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->where('is_correct', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')
            ->withPivot(['exam_section_id', 'order', 'points'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
