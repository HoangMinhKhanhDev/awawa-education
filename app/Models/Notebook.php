<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubject;
use App\Support\NotebookConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    /**
     * @return Collection<int, self>
     */
    public static function forUser(User $user): Collection
    {
        return static::query()
            ->where('owner_id', $user->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Notebook mặc định: tạo mới nếu giáo viên chưa có notebook nào.
     */
    public static function defaultFor(User $user): self
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

    public static function maxPerUser(): int
    {
        return NotebookConfig::maxNotebooksPerUser();
    }

    public static function nextTitleFor(User $user): string
    {
        $count = static::query()->where('owner_id', $user->id)->count();

        return 'Notebook '.($count + 1);
    }

    public static function createFor(User $user, ?string $title = null): self
    {
        $title = trim((string) $title);
        $title = $title === '' ? static::nextTitleFor($user) : $title;

        return static::query()->create([
            'owner_id' => $user->id,
            'subject_id' => $user->subject_id,
            'title' => Str::limit($title, 120, ''),
            'description' => null,
        ]);
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->isOwnedBy($user) && static::query()->where('owner_id', $user->id)->count() > 1;
    }
}
