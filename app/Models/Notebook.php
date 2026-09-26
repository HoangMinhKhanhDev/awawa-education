<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notebook extends Model
{
    use BelongsToSubject, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'owner_id',
        'title',
        'description',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NotebookSource::class)->orderBy('order');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(NotebookChunk::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NotebookMessage::class)->orderBy('id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(NotebookArtifact::class)->latest();
    }

    public function enabledSourceIds(): array
    {
        return $this->sources()
            ->where('is_enabled', true)
            ->where('status', 'ready')
            ->pluck('id')
            ->all();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id === $user->id;
    }

    public static function forOwner(User $user): self
    {
        return static::query()->firstOrCreate(
            ['owner_id' => $user->id],
            [
                'subject_id' => $user->subject_id,
                'title' => 'Notebook '.($user->subject?->name ?? ''),
                'description' => 'Không gian làm việc AI của giáo viên.',
            ],
        );
    }
}
