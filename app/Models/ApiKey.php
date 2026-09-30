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
        'hash_version',
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
            'hash_version' => 'hmac',
            'scopes' => $attributes['scopes'] ?? [ApiScope::ProfileRead->value],
            'rate_limit_per_minute' => $attributes['rate_limit_per_minute'] ?? 60,
            'is_active' => $attributes['is_active'] ?? true,
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
        ]);

        return [$apiKey, $plain];
    }

    public static function hashKey(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('app.key'));
    }

    /**
     * Hash cũ (trước Phase 1): SHA-256 trần, không HMAC. Giữ lại để verify
     * các key production đã phát hành — plaintext đã mất nên không thể
     * recompute HMAC cho chúng, chỉ có thể đối chiếu legacy rồi upgrade dần.
     */
    public static function legacyHashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isLegacyHash(): bool
    {
        return ($this->hash_version ?? 'hmac') === 'legacy';
    }

    public function verifyPlaintext(string $plain): bool
    {
        if (hash_equals($this->key_hash, static::hashKey($plain))) {
            return true;
        }

        return hash_equals($this->key_hash, static::legacyHashKey($plain));
    }

    /**
     * Tìm key còn hiệu lực từ plaintext, thử HMAC trước rồi fallback legacy.
     * Khớp legacy thì tự upgrade hash lên HMAC ngay (không đổi plaintext,
     * key của khách vẫn chạy tiếp, lần sau verify thẳng HMAC).
     */
    public static function findForPlaintext(string $plain): ?self
    {
        $hmac = static::hashKey($plain);

        $key = static::query()->usable()->where('key_hash', $hmac)->first();

        if ($key !== null) {
            return $key;
        }

        $legacy = static::query()->usable()->where('key_hash', static::legacyHashKey($plain))->first();

        if ($legacy !== null) {
            $legacy->forceFill(['key_hash' => $hmac, 'hash_version' => 'hmac'])->save();

            return $legacy->refresh();
        }

        return null;
    }

    /**
     * Xoay key: sinh plaintext mới, upgrade hash lên HMAC. Plaintext mới chỉ
     * trả về 1 lần — admin phải sao chép ngay, key cũ mất hiệu lực tức thì.
     *
     * Không cho xoay key đã thu hồi / đang tạm khóa: muốn dùng lại phải kích
     * hoạt tường minh trước, tránh hồi sinh nhầm key chết.
     *
     * @return string plaintext mới
     */
    public function rotate(): string
    {
        abort_if($this->isRevoked() || ! $this->is_active, 422, 'Khóa đã thu hồi hoặc đang tạm khóa, không thể xoay.');

        $plain = 'awawa_'.Str::random(40);

        $this->forceFill([
            'prefix' => mb_substr($plain, 0, 12),
            'key_hash' => static::hashKey($plain),
            'hash_version' => 'hmac',
            'revoked_at' => null,
            'is_active' => true,
        ])->save();

        return $plain;
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
