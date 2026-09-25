<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Đang làm',
            self::Submitted => 'Chờ chấm',
            self::Graded => 'Đã chấm',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'amber',
            self::Submitted => 'blue',
            self::Graded => 'emerald',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::InProgress;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
