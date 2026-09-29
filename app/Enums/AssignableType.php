<?php

namespace App\Enums;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\Exam;
use App\Models\KnowledgeMap;
// Trong `App\Enums` đã có enum `Subject`, nên phải đặt bí danh cho model.
use App\Models\Subject as SubjectModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Các loại nội dung giáo viên có thể giao cho đội tuyển.
 *
 * Giá trị là slug ngắn (`exam`, `document`...). Cột `assignable_type` của quan
 * hệ morph vẫn lưu FQCN vì app không dùng morphMap; xem `tryFromModelClass()`.
 */
enum AssignableType: string
{
    case Exam = 'exam';

    case Document = 'document';

    case Announcement = 'announcement';

    case KnowledgeMap = 'knowledge_map';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Exam => Exam::class,
            self::Document => Document::class,
            self::Announcement => Announcement::class,
            self::KnowledgeMap => KnowledgeMap::class,
        };
    }

    /**
     * Tra cứu ngược từ tên lớp model thật về loại nội dung.
     *
     * Cột `assignable_type` lưu FQCN (VD: `App\Models\Document`) vì app không
     * dùng morphMap, nên không thể `tryFrom()` trực tiếp lên slug.
     */
    public static function tryFromModelClass(string $class): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->modelClass() === $class) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Tra cứu ngược từ model thật về loại nội dung.
     */
    public static function fromModel(Model $model): ?self
    {
        foreach (self::cases() as $case) {
            $class = $case->modelClass();

            if ($model instanceof $class) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Đề thi / bài tập',
            self::Document => 'Tài liệu',
            self::Announcement => 'Thông báo',
            self::KnowledgeMap => 'Sơ đồ kiến thức',
        };
    }

    public function labelPlural(): string
    {
        return match ($this) {
            self::Exam => 'Đề thi và bài tập',
            self::Document => 'Tài liệu',
            self::Announcement => 'Thông báo',
            self::KnowledgeMap => 'Sơ đồ kiến thức',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Exam => 'cap',
            self::Document => 'doc',
            self::Announcement => 'bell',
            self::KnowledgeMap => 'map',
        };
    }

    public function feature(): ?SubjectFeature
    {
        return match ($this) {
            self::Exam => null,
            self::Document => SubjectFeature::Documents,
            self::Announcement => SubjectFeature::Announcements,
            self::KnowledgeMap => SubjectFeature::KnowledgeMap,
        };
    }

    /**
     * Chỉ đề thi có vòng nộp bài và chấm điểm, các loại khác học sinh tự bấm xem xong.
     */
    public function requiresSubmission(): bool
    {
        return $this === self::Exam;
    }

    public function studentUrl(Model $model): string
    {
        return match ($this) {
            self::Exam => route('student.take', $model),
            self::Document => $model instanceof Document ? $model->viewerUrl() : (string) $model->url(),
            self::Announcement => route('info'),
            self::KnowledgeMap => route('map'),
        };
    }

    public function teacherUrl(Model $model): string
    {
        return match ($this) {
            self::Exam => route('studio.grading', $model),
            self::Document => route('studio.documents'),
            self::Announcement => route('studio.announcements'),
            self::KnowledgeMap => route('map'),
        };
    }

    /**
     * Nội dung phụ thuộc môn, nên phải bật đúng tính năng của môn mới giao được.
     */
    public function allowsFor(?SubjectModel $subject): bool
    {
        $feature = $this->feature();

        if ($feature === null || $subject === null) {
            return $feature === null;
        }

        return $subject->hasFeature($feature);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
