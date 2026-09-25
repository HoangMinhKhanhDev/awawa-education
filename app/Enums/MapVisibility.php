<?php

namespace App\Enums;

enum MapVisibility: string
{
    case Private = 'private';
    case Subject = 'subject';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Riêng tư',
            self::Subject => 'Cả đội tuyển',
            self::Link => 'Ai có liên kết',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Private => 'Chỉ mình bạn xem được.',
            self::Subject => 'Học sinh và giáo viên trong môn xem được.',
            self::Link => 'Người có liên kết chia sẻ đều xem được.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $visibility) => $visibility->value, self::cases());
    }
}
