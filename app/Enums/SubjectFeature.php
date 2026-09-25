<?php

namespace App\Enums;

/**
 * Các tính năng có thể bật/tắt cho từng môn.
 *
 * Mỗi môn có một bộ tính năng khác nhau. Studio, menu và các module
 * nghiệp vụ sẽ được render động dựa trên feature được bật cho môn đó,
 * giúp tách biệt hoàn toàn giữa các môn.
 */
enum SubjectFeature: string
{
    case QuestionBank = 'question_bank';
    case Exams = 'exams';
    case Assignments = 'assignments';
    case Documents = 'documents';
    case KnowledgeMap = 'knowledge_map';
    case Announcements = 'announcements';
    case Rankings = 'rankings';
    case Grading = 'grading';
    case AiTools = 'ai_tools';

    public function label(): string
    {
        return match ($this) {
            self::QuestionBank => 'Ngân hàng câu hỏi',
            self::Exams => 'Đề thi',
            self::Assignments => 'Bài tập về nhà',
            self::Documents => 'Tài liệu học tập',
            self::KnowledgeMap => 'Sơ đồ kiến thức',
            self::Announcements => 'Thông báo',
            self::Rankings => 'Bảng xếp hạng',
            self::Grading => 'Chấm điểm',
            self::AiTools => 'Công cụ AI',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::QuestionBank => 'Kho câu hỏi dùng chung để tạo đề và giao bài.',
            self::Exams => 'Tạo, giao và tổ chức thi cho học sinh trong đội tuyển.',
            self::Assignments => 'Giao bài tập về nhà và theo dõi tiến độ nộp bài.',
            self::Documents => 'Đăng tải tài liệu học tập cho học sinh.',
            self::KnowledgeMap => 'Bảng trắng sơ đồ kiến thức dạng node và liên kết.',
            self::Announcements => 'Gửi thông báo tới học sinh trong đội tuyển môn.',
            self::Rankings => 'Bảng xếp hạng điểm tổng giữa các học sinh của môn.',
            self::Grading => 'Chấm điểm tự luận và tổng hợp kết quả.',
            self::AiTools => 'Sinh đề/tài liệu bằng AI theo môn.',
        };
    }

    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::AiTools => false,
            default => true,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $feature) => $feature->value, self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function defaultEnabledValues(): array
    {
        return array_values(array_map(
            fn (self $feature) => $feature->value,
            array_filter(self::cases(), fn (self $feature) => $feature->defaultEnabled()),
        ));
    }
}
