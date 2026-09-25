<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Quản trị viên',
            self::Teacher => 'Giáo viên',
            self::Student => 'Học sinh',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'violet',
            self::Teacher => 'blue',
            self::Student => 'emerald',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
