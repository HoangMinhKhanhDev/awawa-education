<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class NotebookSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    /** Memo trong request: NotebookConfig gọi get() 5-7 lần mỗi request chat. */
    protected static array $memo = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, static::$memo)) {
            return static::$memo[$key] ?? $default;
        }

        $value = Cache::remember('notebook-setting:v1:'.$key, 300, function () use ($key): ?string {
            $row = static::query()->where('key', $key)->first();

            if ($row === null || $row->value === null) {
                return null;
            }

            if ($row->is_encrypted) {
                try {
                    return Crypt::decryptString($row->value);
                } catch (\Throwable) {
                    return null;
                }
            }

            return $row->value;
        });

        static::$memo[$key] = $value;

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value, bool $encrypted = false): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value === null ? null : ($encrypted ? Crypt::encryptString($value) : $value),
                'is_encrypted' => $encrypted && $value !== null,
            ],
        );

        unset(static::$memo[$key]);
        Cache::forget('notebook-setting:v1:'.$key);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();

        unset(static::$memo[$key]);
        Cache::forget('notebook-setting:v1:'.$key);
    }

    public static function flushMemo(): void
    {
        static::$memo = [];
    }
}
