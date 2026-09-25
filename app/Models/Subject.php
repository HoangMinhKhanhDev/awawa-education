<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SubjectFeature as FeatureEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Subject extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'color',
        'description',
        'is_active',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubjectFeature::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::Teacher->value);
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::Student->value);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * Danh sách feature đang bật của môn (có cache để giảm truy vấn mỗi request).
     *
     * @return array<int, string>
     */
    public function enabledFeatureKeys(): array
    {
        return Cache::remember($this->featureCacheKey(), now()->addMinutes(15), function (): array {
            return $this->features()
                ->where('is_enabled', true)
                ->pluck('feature')
                ->map(fn ($feature) => $feature instanceof FeatureEnum ? $feature->value : (string) $feature)
                ->all();
        });
    }

    public function hasFeature(FeatureEnum|string $feature): bool
    {
        $key = $feature instanceof FeatureEnum ? $feature->value : $feature;

        return in_array($key, $this->enabledFeatureKeys(), true);
    }

    public function featureCacheKey(): string
    {
        return 'subject:'.$this->getKey().':features';
    }

    public function forgetFeatureCache(): void
    {
        Cache::forget($this->featureCacheKey());
    }
}
