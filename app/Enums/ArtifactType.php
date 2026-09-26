<?php

namespace App\Enums;

enum ArtifactType: string
{
    case Questions = 'questions';
    case Exam = 'exam';
    case Document = 'document';
    case Flashcards = 'flashcards';
    case MindMap = 'mindmap';
    case StudyGuide = 'study_guide';
    case Briefing = 'briefing';

    public function label(): string
    {
        return match ($this) {
            self::Questions => 'Câu hỏi',
            self::Exam => 'Đề thi',
            self::Document => 'Tài liệu / tóm tắt',
            self::Flashcards => 'Thẻ ghi nhớ',
            self::MindMap => 'Sơ đồ tư duy',
            self::StudyGuide => 'Đề cương ôn tập',
            self::Briefing => 'Bản tin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Questions => 'Tạo câu hỏi trắc nghiệm/tự luận/điền khuyết từ nguồn.',
            self::Exam => 'Soạn đề có cấu trúc phần và câu hỏi.',
            self::Document => 'Viết tài liệu hoặc tóm tắt dạng Markdown.',
            self::Flashcards => 'Thẻ hỏi–đáp để học nhanh.',
            self::MindMap => 'Cây kiến thức node + liên kết, xuất sang Sơ đồ kiến thức.',
            self::StudyGuide => 'Đề cương ôn tập theo nguồn.',
            self::Briefing => 'Bản tin tổng hợp nhanh.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Questions => 'check',
            self::Exam => 'cap',
            self::Document => 'mail',
            self::Flashcards => 'bolt',
            self::MindMap => 'map',
            self::StudyGuide => 'chart',
            self::Briefing => 'bell',
        };
    }

    public function isJson(): bool
    {
        return in_array($this, [self::Questions, self::Exam, self::Flashcards, self::MindMap], true);
    }

    public function publishTarget(): string
    {
        return match ($this) {
            self::Questions => 'Ngân hàng câu hỏi',
            self::Exam => 'Đề thi (bản nháp)',
            self::MindMap => 'Sơ đồ kiến thức',
            default => 'Tài liệu của môn',
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
