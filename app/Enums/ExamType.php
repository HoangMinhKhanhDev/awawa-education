<?php

namespace App\Enums;

enum ExamType: string
{
    case Exam = 'exam';
    case Assignment = 'assignment';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Đề thi',
            self::Assignment => 'Bài tập',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Exam => 'studio.exams',
            self::Assignment => 'studio.assignments',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
