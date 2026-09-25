<?php

namespace App\Enums;

enum AiPurpose: string
{
    case QuestionGeneration = 'question_generation';
    case DocumentGeneration = 'document_generation';
    case ConnectionTest = 'connection_test';

    public function label(): string
    {
        return match ($this) {
            self::QuestionGeneration => 'Sinh câu hỏi',
            self::DocumentGeneration => 'Sinh tài liệu',
            self::ConnectionTest => 'Kiểm tra kết nối',
        };
    }
}
