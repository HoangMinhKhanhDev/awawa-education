<?php

namespace App\Enums;

enum Difficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Dễ',
            self::Medium => 'Trung bình',
            self::Hard => 'Khó',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Easy => 'emerald',
            self::Medium => 'amber',
            self::Hard => 'red',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $difficulty) => $difficulty->value, self::cases());
    }
}
