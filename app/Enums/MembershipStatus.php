<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Pending => 'Chờ duyệt',
            self::Removed => 'Đã rời đội',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
