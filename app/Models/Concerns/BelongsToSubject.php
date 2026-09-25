<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SubjectScope;
use App\Models\Subject;
use App\Support\SubjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gắn model với một môn và tự động áp scope + điền subject_id.
 *
 * @mixin Model
 */
trait BelongsToSubject
{
    protected static function bootBelongsToSubject(): void
    {
        static::addGlobalScope(new SubjectScope);

        static::creating(function (Model $model): void {
            $context = app(SubjectContext::class);

            if ($context->id() !== null && empty($model->getAttribute('subject_id'))) {
                $model->setAttribute('subject_id', $context->id());
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scopeForSubject(Builder $query, Subject|int $subject): Builder
    {
        $subjectId = $subject instanceof Subject ? $subject->getKey() : $subject;

        return $query->where($this->getTable().'.subject_id', $subjectId);
    }

    public function scopeWithoutSubjectScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SubjectScope::class);
    }
}
