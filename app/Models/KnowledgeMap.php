<?php

namespace App\Models;

use App\Enums\MapVisibility;
use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class KnowledgeMap extends Model
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
        'visibility',
        'share_token',
        'current_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => MapVisibility::class,
            'current_version' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeMapVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(KnowledgeMapVersion::class)->latestOfMany('version');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id === $user->id;
    }

    public function ensureShareToken(): string
    {
        if (blank($this->share_token)) {
            $this->forceFill(['share_token' => Str::random(32)])->save();
        }

        return $this->share_token;
    }

    public function shareUrl(): string
    {
        return route('maps.shared', $this->ensureShareToken());
    }
}
