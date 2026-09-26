<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        $row = static::query()->where('key', $key)->first();

        if ($row === null || $row->value === null) {
            return $default;
        }

        if ($row->is_encrypted) {
            try {
                return Crypt::decryptString($row->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $row->value;
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
    }
}
