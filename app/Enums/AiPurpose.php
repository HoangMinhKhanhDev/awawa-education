<?php

namespace App\Enums;

enum AiPurpose: string
{
    case QuestionGeneration = 'question_generation';
    case DocumentGeneration = 'document_generation';
    case ConnectionTest = 'connection_test';
    case Chat = 'chat';
    case Artifact = 'artifact';
    case WebSourcePicker = 'web_source_picker';

    public function label(): string
    {
        return match ($this) {
            self::QuestionGeneration => 'Sinh câu hỏi',
            self::DocumentGeneration => 'Sinh tài liệu',
            self::ConnectionTest => 'Kiểm tra kết nối',
            self::Chat => 'Hỏi đáp',
            self::Artifact => 'Tạo nội dung',
            self::WebSourcePicker => 'Chọn nguồn web',
        };
    }
}
