<?php

namespace App\Enums;

enum ApiScope: string
{
    case ProfileRead = 'profile:read';
    case DocumentsRead = 'documents:read';
    case ExamsRead = 'exams:read';
    case ScoresRead = 'scores:read';
    case SubmissionsWrite = 'submissions:write';
    case AiGenerate = 'ai:generate';

    public function label(): string
    {
        return match ($this) {
            self::ProfileRead => 'Đọc hồ sơ',
            self::DocumentsRead => 'Đọc tài liệu',
            self::ExamsRead => 'Đọc đề thi',
            self::ScoresRead => 'Đọc điểm số',
            self::SubmissionsWrite => 'Ghi bài nộp',
            self::AiGenerate => 'Sinh nội dung bằng AI',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $scope) => $scope->value, self::cases());
    }
}
