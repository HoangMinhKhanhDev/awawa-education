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

    /**
     * Màu nhấn riêng cho từng loại, để phân biệt nhanh trong Studio.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Questions => 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            self::Exam => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            self::Document => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Flashcards => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            self::MindMap => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            self::StudyGuide => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
            self::Briefing => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
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
