<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Support\SafeCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Điểm thực lực của học sinh, dùng chung cho bảng xếp hạng, bảng đội,
 * trang chi tiết và file Excel để ba nơi một con số.
 *
 * Công thức: mỗi đề chỉ tính lần làm đầu đã chấm xong, trung bình có trọng
 * số theo hệ số đề; làm từ 4 đề trở lên thì bỏ 1 đề thấp nhất.
 */
class StudentAbility
{
    /**
     * @return Collection<int, array{student_id: int, average: float, exams: int, best_percent: float, retakes: int, details: array<int, array{exam_id: int, exam_title: string, weight: float, first_percent: float|null, best_percent: float, attempts: int}>}>
     */
    public function rows(?int $subjectId = null): Collection
    {
        // Cache mảng thô chứ không cache Collection: giá trị cache phải sống sót
        // qua các lần deploy (class PHP đổi thì cache Eloquent unserialize ra
        // `__PHP_Incomplete_Class` và làm vỡ trang). Xem `SafeCache`.
        $rows = SafeCache::remember(
            $this->cacheKey($subjectId),
            120,
            fn (): array => $this->computeRows($subjectId)->all(),
        );

        return collect($rows);
    }

    public static function forgetCache(?int $subjectId = null): void
    {
        Cache::forget(self::keyFor($subjectId));

        if ($subjectId !== null) {
            Cache::forget('class-exam-stats:v2:subject:'.$subjectId);
            Cache::forget('class-violations:v1:subject:'.$subjectId);
        }
    }

    protected function cacheKey(?int $subjectId): string
    {
        return self::keyFor($subjectId);
    }

    protected static function keyFor(?int $subjectId): string
    {
        // `v2` vì `v1` lưu Collection, cache đó không dùng được nữa.
        return 'ability:v2:subject:'.($subjectId ?? 'all');
    }

    /**
     * @return Collection<int, array{student_id: int, average: float, exams: int, best_percent: float, retakes: int, details: array<int, array{exam_id: int, exam_title: string, weight: float, first_percent: float|null, best_percent: float, attempts: int}>}>
     */
    protected function computeRows(?int $subjectId = null): Collection
    {
        $attempts = ExamAttempt::query()
            ->where('status', AttemptStatus::Graded->value)
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->get(['student_id', 'exam_id', 'attempt_no', 'score', 'max_score']);

        if ($attempts->isEmpty()) {
            return collect();
        }

        $exams = Exam::query()
            ->whereIn('id', $attempts->pluck('exam_id')->unique()->all())
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->get(['id', 'title', 'settings'])
            ->keyBy('id');

        return $attempts
            ->groupBy('student_id')
            ->map(fn (Collection $studentAttempts, int $studentId): array => $this->summarize(
                $studentId,
                $studentAttempts->groupBy('exam_id'),
                $exams,
            ))
            ->values();
    }

    /**
     * @param  Collection<int, Collection<int, ExamAttempt>>  $byExam
     * @param  Collection<int, Exam>  $exams
     * @return array{student_id: int, average: float, exams: int, best_percent: float, retakes: int, details: array<int, array{exam_id: int, exam_title: string, weight: float, first_percent: float|null, best_percent: float, attempts: int}>}
     */
    protected function summarize(int $studentId, Collection $byExam, Collection $exams): array
    {
        $details = $byExam->map(function (Collection $rows, int $examId) use ($exams): array {
            $ordered = $rows->sortBy('attempt_no')->values();
            $exam = $exams->get($examId);

            return [
                'exam_id' => $examId,
                'exam_title' => $exam?->title ?? 'Đề đã xoá',
                'weight' => $exam?->weight() ?? 1.0,
                'first_percent' => $this->percentOf($ordered->first()),
                'best_percent' => $ordered->map(fn (ExamAttempt $attempt): ?float => $this->percentOf($attempt))->filter(fn (?float $value): bool => $value !== null)->max() ?? 0.0,
                'attempts' => $rows->count(),
            ];
        })->values();

        // Bỏ 1 đề thấp nhất khi đã làm từ 4 đề trở lên.
        $counted = $details->count() >= 4
            ? $details->sortBy('first_percent')->slice(1)->values()
            : $details;

        $weightTotal = $counted->sum('weight');

        return [
            'student_id' => $studentId,
            'average' => $weightTotal > 0
                ? round($counted->sum(fn (array $row): float => ($row['first_percent'] ?? 0.0) * $row['weight']) / $weightTotal, 2)
                : 0.0,
            'exams' => $details->count(),
            'best_percent' => $details->max('best_percent') ?? 0.0,
            'retakes' => $details->sum('attempts'),
            // Mảng thô để serialize được: Collection lồng bên trong sẽ hỏng khi
            // unserialize sau một lần deploy.
            'details' => $details->all(),
        ];
    }

    protected function percentOf(?ExamAttempt $attempt): ?float
    {
        if ($attempt === null) {
            return null;
        }

        $max = (float) $attempt->max_score;

        if ($max <= 0) {
            return null;
        }

        return round(((float) $attempt->score / $max) * 100, 2);
    }
}
