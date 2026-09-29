<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Support\SimpleXlsx;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Xuất bảng điểm ra file .xlsx cho giáo viên.
 */
class StudentExportService
{
    public function __construct(protected StudentAbility $ability) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function teamWorkbook(int $subjectId, string $subjectName): array
    {
        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->where('subject_id', $subjectId)
            ->get();

        $abilities = $this->ability->rows()->keyBy('student_id');

        $rows = $members->map(function (TeamMembership $membership) use ($abilities): array {
            $row = $abilities->get($membership->student_id);

            return [
                $membership->student?->name ?? '',
                $membership->student?->email ?? '',
                $row['average'] ?? 0.0,
                $row['exams'] ?? 0,
                $row['retakes'] ?? 0,
                $row['best_percent'] ?? 0.0,
            ];
        })->all();

        $xlsx = (new SimpleXlsx)->addSheet(
            'Cả đội',
            ['Họ tên', 'Email', 'Điểm thực lực (%)', 'Số đề', 'Lượt làm', 'Tốt nhất (%)'],
            $rows,
            [28, 32, 16, 10, 10, 12],
        );

        return [
            'filename' => $this->filename('bang-diem-'.$subjectName),
            'content' => $xlsx->build(),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function examGradesWorkbook(Exam $exam): array
    {
        $questions = $exam->examQuestions()->with('question')->orderBy('order')->get();

        $headers = array_merge(
            ['Họ tên', 'Email'],
            $questions->map(fn ($examQuestion, int $index): string => 'Câu '.($index + 1))->all(),
            ['Tổng', 'Tỉ lệ (%)'],
        );

        $attempts = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', AttemptStatus::Graded->value)
            ->with(['student', 'answers'])
            ->get()
            ->groupBy('student_id');

        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->where('subject_id', $exam->subject_id)
            ->get();

        $rows = $members->map(function (TeamMembership $membership) use ($attempts, $questions): array {
            /** @var Collection<int, ExamAttempt> $studentAttempts */
            $studentAttempts = $attempts->get($membership->student_id, collect());
            $latest = $studentAttempts->sortByDesc('attempt_no')->first();

            $points = $questions->map(function ($examQuestion) use ($latest): int|float|string {
                if ($latest === null) {
                    return '';
                }

                $answer = $latest->answers->firstWhere('question_id', $examQuestion->question_id);

                return $answer?->awarded_points === null ? '' : (float) $answer->awarded_points;
            })->all();

            return array_merge(
                [$membership->student?->name ?? '', $membership->student?->email ?? ''],
                $points,
                $latest === null
                    ? ['', '']
                    : [(float) $latest->score, $latest->percent()],
            );
        })->all();

        $widths = array_merge([28, 32], array_fill(0, $questions->count(), 10), [10, 10]);

        $xlsx = (new SimpleXlsx)->addSheet('Điểm '.$exam->title, $headers, $rows, $widths);

        return [
            'filename' => $this->filename('diem-'.$exam->title),
            'content' => $xlsx->build(),
        ];
    }

    protected function filename(string $base): string
    {
        return Str::slug($base) ?: 'bang-diem'.'-'.now()->format('Y-m-d').'.xlsx';
    }
}
