<?php

namespace App\Models;

use App\Enums\ApiScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'prefix',
        'key_hash',
        'scopes',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tạo khóa mới và trả về [model, chuỗi khóa gốc] — khóa gốc chỉ hiện một lần.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes): array
    {
        $plain = 'awawa_'.Str::random(40);

        $apiKey = static::create([
            'name' => $attributes['name'],
            'prefix' => mb_substr($plain, 0, 12),
            'key_hash' => static::hashKey($plain),
            'scopes' => $attributes['scopes'] ?? ApiScope::values(),
            'rate_limit_per_minute' => $attributes['rate_limit_per_minute'] ?? 60,
            'is_active' => $attributes['is_active'] ?? true,
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
        ]);

        return [$apiKey, $plain];
    }

    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isRevoked() && ! $this->isExpired();
    }

    public function maskedKey(): string
    {
        return $this->prefix.'••••••••••••';
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
