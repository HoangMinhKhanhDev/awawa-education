<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case Essay = 'essay';
    case FillBlank = 'fill_blank';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Trắc nghiệm',
            self::Essay => 'Tự luận',
            self::FillBlank => 'Điền khuyết',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::MultipleChoice;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
