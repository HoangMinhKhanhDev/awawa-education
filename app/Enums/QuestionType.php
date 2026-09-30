<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';

    case TrueFalse = 'true_false';

    /**
     * Chùm đúng/sai chuẩn BGD: một đoạn ngữ cảnh chung + đúng 4 mệnh đề a–d,
     * chấm theo nấc (2 đúng 0.25, 3 đúng 0.5, 4 đúng 1.0 nhân điểm chùm).
     * Bốn mệnh đề lưu như 4 option, `is_correct` là đáp án từng mệnh đề.
     */
    case TrueFalseCluster = 'true_false_cluster';

    case Essay = 'essay';

    case FillBlank = 'fill_blank';

    /** Giá trị lưu trong cột `answer` cho câu đúng/sai. */
    public const TRUE = 'true';

    public const FALSE = 'false';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Trắc nghiệm',
            self::TrueFalse => 'Đúng / sai',
            self::TrueFalseCluster => 'Chùm Đúng / sai (BGD)',
            self::Essay => 'Tự luận',
            self::FillBlank => 'Điền khuyết',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::MultipleChoice || $this === self::TrueFalseCluster;
    }

    /**
     * Loại này bắt buộc phải có đáp án.
     */
    public function requiresAnswer(): bool
    {
        return $this === self::FillBlank || $this === self::TrueFalse;
    }

    /**
     * Hai lựa chọn của câu đúng/sai, khoá là giá trị lưu trong database.
     *
     * @return array<string, string>
     */
    public function trueFalseChoices(): array
    {
        return [
            self::TRUE => 'Đúng',
            self::FALSE => 'Sai',
        ];
    }

    /**
     * Nhãn tiếng Việt của một giá trị đúng/sai, kể cả khi giá trị đã bị AI viết
     * theo kiểu khác ("Đúng", "true", "có"...).
     */
    public function trueFalseLabel(?string $value): string
    {
        return $this->trueFalseChoices()[self::normalizeTruthy($value) ?? ''] ?? 'Chưa chọn';
    }

    /**
     * Chuẩn hoá mọi biến thể của đáp án đúng/sai về `true` hoặc `false`.
     *
     * Trả về null nếu không nhận ra, để phân biệt "sai" với "không hợp lệ".
     */
    public static function normalizeTruthy(?string $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'đúng', 'dung', 'true', '1', 'có', 'co', 'y', 'yes' => self::TRUE,
            'sai', 'false', '0', 'không', 'khong', 'n', 'no' => self::FALSE,
            default => null,
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
