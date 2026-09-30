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
 * Xuất bảng điểm cho giáo viên: file .xlsx chuẩn và file .csv dự phòng
 * (mở được mọi nơi, kể cả máy không đọc được xlsx lạ).
 */
class StudentExportService
{
    public function __construct(protected StudentAbility $ability) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function teamWorkbook(int $subjectId, string $subjectName): array
    {
        $xlsx = (new SimpleXlsx)->addSheet(
            'Cả đội',
            $this->teamHeaders(),
            $this->teamRows($subjectId),
            [28, 32, 16, 10, 10, 12],
        );

        return [
            'filename' => $this->filename('bang-diem-'.$subjectName, 'xlsx'),
            'content' => $xlsx->build(),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function teamCsv(int $subjectId, string $subjectName): array
    {
        return [
            'filename' => $this->filename('bang-diem-'.$subjectName, 'csv'),
            'content' => $this->toCsv($this->teamHeaders(), $this->teamRows($subjectId)),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function examGradesWorkbook(Exam $exam): array
    {
        [$headers, $rows, $widths] = $this->examGradesTable($exam);

        $xlsx = (new SimpleXlsx)->addSheet('Điểm '.$exam->title, $headers, $rows, $widths);

        return [
            'filename' => $this->filename('diem-'.$exam->title, 'xlsx'),
            'content' => $xlsx->build(),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function examGradesCsv(Exam $exam): array
    {
        [$headers, $rows] = $this->examGradesTable($exam);

        return [
            'filename' => $this->filename('diem-'.$exam->title, 'csv'),
            'content' => $this->toCsv($headers, $rows),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function teamHeaders(): array
    {
        return ['Họ tên', 'Email', 'Điểm thực lực (%)', 'Số đề', 'Lượt làm', 'Tốt nhất (%)'];
    }

    /**
     * @return array<int, array<int, int|float|string>>
     */
    protected function teamRows(int $subjectId): array
    {
        $abilities = $this->ability->rows($subjectId)->keyBy('student_id');
        $rows = [];

        // chunk() thay vì get() toàn bộ để đội đông không phình RAM.
        TeamMembership::query()
            ->active()
            ->with('student')
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->chunk(500, function (Collection $members) use ($abilities, &$rows): void {
                foreach ($members as $membership) {
                    $row = $abilities->get($membership->student_id);

                    $rows[] = [
                        $membership->student?->name ?? '',
                        $membership->student?->email ?? '',
                        $row['average'] ?? 0.0,
                        $row['exams'] ?? 0,
                        $row['retakes'] ?? 0,
                        $row['best_percent'] ?? 0.0,
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, int|float|string>>, 2: array<int, float>}
     */
    protected function examGradesTable(Exam $exam): array
    {
        $questions = $exam->examQuestions()->with('question')->orderBy('order')->get();

        $headers = array_merge(
            ['Họ tên', 'Email'],
            $questions->map(fn ($examQuestion, int $index): string => 'Câu '.($index + 1))->all(),
            ['Tổng', 'Tỉ lệ (%)'],
        );

        $attempts = collect();

        ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', AttemptStatus::Graded->value)
            ->with(['student', 'answers'])
            ->orderBy('id')
            ->chunk(500, function (Collection $chunk) use ($attempts): void {
                foreach ($chunk as $attempt) {
                    $attempts->push($attempt);
                }
            });

        $attempts = $attempts->groupBy('student_id');

        $members = collect();

        TeamMembership::query()
            ->active()
            ->with('student')
            ->where('subject_id', $exam->subject_id)
            ->orderBy('id')
            ->chunk(500, function (Collection $chunk) use ($members): void {
                foreach ($chunk as $membership) {
                    $members->push($membership);
                }
            });

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
                    : [(float) $latest->score, $latest->percent() ?? ''],
            );
        })->all();

        $widths = array_merge([28, 32], array_fill(0, $questions->count(), 10), [10, 10]);

        return [$headers, $rows, $widths];
    }

    /**
     * CSV phân cách bằng chấm phẩy + BOM để Excel tiếng Việt mở đúng font,
     * mỗi ô một cột (không bị dồn như file comma trên máy Việt).
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    protected function toCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Không tạo được nội dung CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value): string => $value === null ? '' : (string) $value, $row), ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    protected function filename(string $base, string $extension): string
    {
        return (Str::slug($base) ?: 'bang-diem').'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
