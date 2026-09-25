<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Published => 'Đã giao',
            self::Closed => 'Đã đóng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Published => 'emerald',
            self::Closed => 'red',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
