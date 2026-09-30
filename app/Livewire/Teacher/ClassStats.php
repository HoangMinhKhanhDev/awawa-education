<?php

namespace App\Livewire\Teacher;

use App\Enums\AttemptStatus;
use App\Models\AssignmentReceipt;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\StudentAbility;
use App\Support\SafeCache;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Thống kê lớp')]
class ClassStats extends Component
{
    public function mount(): void
    {
        Gate::authorize('manageStudents', User::class);
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        if ($subject === null) {
            return view('livewire.teacher.class-stats', [
                'subject' => null,
                'totalMembers' => 0,
                'classAverage' => null,
                'distribution' => [],
                'examStats' => collect(),
                'completion' => ['completed' => 0, 'opened' => 0, 'pending' => 0],
                'daily' => collect(),
                'trend' => collect(),
                'violations' => 0,
                'attention' => collect(),
            ]);
        }

        $abilities = app(StudentAbility::class)->rows($subject->id)->keyBy('student_id');

        $distribution = [
            ['label' => 'Giỏi (≥ 8)', 'count' => 0, 'color' => 'bg-success'],
            ['label' => 'Khá (6.5 – 8)', 'count' => 0, 'color' => 'bg-brand-600 dark:bg-brand-400'],
            ['label' => 'Trung bình (5 – 6.5)', 'count' => 0, 'color' => 'bg-warning dark:bg-amber-400'],
            ['label' => 'Yếu (< 5)', 'count' => 0, 'color' => 'bg-signal dark:bg-red-400'],
            ['label' => 'Chưa có bài', 'count' => 0, 'color' => 'bg-slate-300 dark:bg-slate-600'],
        ];

        $memberIds = TeamMembership::query()->active()->where('subject_id', $subject->id)->pluck('student_id')->all();
        $withScores = $abilities->keyBy('student_id');
        $withScores = $abilities->keyBy('student_id');

        foreach ($memberIds as $studentId) {
            $average = $withScores->get($studentId)['average'] ?? null;

            if ($average === null) {
                $distribution[4]['count']++;
            } elseif ($average >= 80) {
                $distribution[0]['count']++;
            } elseif ($average >= 65) {
                $distribution[1]['count']++;
            } elseif ($average >= 50) {
                $distribution[2]['count']++;
            } else {
                $distribution[3]['count']++;
            }
        }

        $examStats = $this->examStats($subject->id);
        $completion = $this->assignmentCompletion($memberIds);
        $daily = $this->dailySubmissions($subject->id);
        $trend = $this->weeklyTrend($subject->id);

        $scored = $abilities->filter(fn (array $row): bool => $row['exams'] > 0);

        $members = TeamMembership::query()
            ->active()
            ->where('subject_id', $subject->id)
            ->with('student')
            ->get(['id', 'student_id']);

        $attention = $this->attentionList($members, $abilities);

        $violations = (int) Cache::remember(
            'class-violations:v1:subject:'.$subject->id,
            300,
            fn (): int => (int) ExamAttempt::query()
                ->where('subject_id', $subject->id)
                ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Graded->value])
                ->select('anti_cheat')
                ->get()
                ->sum(fn (ExamAttempt $attempt): int => count($attempt->anti_cheat ?? []))
        );

        return view('livewire.teacher.class-stats', [
            'subject' => $subject,
            'totalMembers' => count($memberIds),
            'classAverage' => $scored->isNotEmpty() ? round($scored->avg('average'), 1) : null,
            'distribution' => $distribution,
            'examStats' => $examStats,
            'completion' => $completion,
            'daily' => $daily,
            'trend' => $trend,
            'violations' => $violations,
            'attention' => $attention,
        ]);
    }

    /**
     * Học sinh cần chú ý: điểm thực lực dưới 5 hoặc hơn 7 ngày không hoạt động.
     *
     * @param  Collection<int, TeamMembership>  $members
     * @param  Collection<int, array>  $abilities
     * @return Collection<int, array{student: User|null, average: float|null, reasons: array<int, string>}>
     */
    protected function attentionList(Collection $members, Collection $abilities): Collection
    {
        $ids = $members->pluck('student_id')->all();

        if ($ids === []) {
            return collect();
        }

        $submitted = ExamAttempt::query()
            ->finished()
            ->whereIn('student_id', $ids)
            ->groupBy('student_id')
            ->pluck(DB::raw('MAX(submitted_at)'), 'student_id');

        $opened = AssignmentReceipt::query()
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck(DB::raw('MAX(opened_at)'), 'user_id');

        $weekAgo = now()->subDays(7);

        return $members
            ->map(function (TeamMembership $membership) use ($abilities, $submitted, $opened, $weekAgo): ?array {
                $row = $abilities->get($membership->student_id);
                $reasons = [];

                if ($row !== null && $row['exams'] > 0 && $row['average'] < 50) {
                    $reasons[] = 'Điểm thực lực '.number_format($row['average'], 0).'%';
                }

                $last = collect([$submitted->get($membership->student_id), $opened->get($membership->student_id)])
                    ->filter()
                    ->map(fn ($value): Carbon => Carbon::parse($value))
                    ->max();

                if ($last === null || $last->lessThan($weekAgo)) {
                    $reasons[] = $last === null ? 'Chưa có hoạt động nào' : 'Im ắng '.$last->diffForHumans();
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'student' => $membership->student,
                    'average' => $row['average'] ?? null,
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Điểm trung bình lần đầu và tốt nhất từng đề + số lượt làm.
     *
     * Cache mảng thô chứ không cache Collection, vì giá trị cache phải sống sót
     * qua các lần deploy. Xem `App\Support\SafeCache`.
     *
     * @return Collection<int, array{id: int, title: string, weight: float, average: float|null, best_average: float|null, attempts: int}>
     */
    protected function examStats(int $subjectId): Collection
    {
        $rows = SafeCache::remember(
            'class-exam-stats:v2:subject:'.$subjectId,
            120,
            fn (): array => $this->computeExamStats($subjectId),
        );

        return collect($rows);
    }

    /**
     * @return array<int, array{id: int, title: string, weight: float, average: float|null, best_average: float|null, attempts: int}>
     */
    protected function computeExamStats(int $subjectId): array
    {
        $exams = Exam::query()->where('subject_id', $subjectId)->orderBy('created_at')->get(['id', 'title', 'settings']);

        if ($exams->isEmpty()) {
            return [];
        }

        $firsts = ExamAttempt::query()
            ->where('subject_id', $subjectId)
            ->where('status', AttemptStatus::Graded->value)
            ->get(['exam_id', 'student_id', 'attempt_no', 'score', 'max_score'])
            ->groupBy('exam_id')
            ->map(function (Collection $rows): array {
                $byStudent = $rows->groupBy('student_id');

                $pick = function (string $mode) use ($byStudent): Collection {
                    return $byStudent->map(function (Collection $studentRows) use ($mode): ?float {
                        $ordered = $studentRows->sortBy('attempt_no')->values();
                        $attempt = $mode === 'first'
                            ? $ordered->first()
                            : $ordered->sortByDesc(fn ($row): float => (float) $row->max_score > 0
                                ? (float) $row->score / (float) $row->max_score
                                : -1)->first();
                        $max = (float) $attempt->max_score;

                        return $max > 0 ? round(((float) $attempt->score / $max) * 100, 2) : null;
                    })->filter(fn (?float $value): bool => $value !== null);
                };

                $first = $pick('first');
                $best = $pick('best');

                return [
                    'average' => $first->isNotEmpty() ? round($first->avg(), 2) : null,
                    'best_average' => $best->isNotEmpty() ? round($best->avg(), 2) : null,
                    'attempts' => $rows->count(),
                ];
            });

        return $exams->map(fn (Exam $exam): array => [
            'id' => $exam->id,
            'title' => $exam->title,
            'weight' => $exam->weight(),
            'average' => $firsts->get($exam->id)['average'] ?? null,
            'best_average' => $firsts->get($exam->id)['best_average'] ?? null,
            'attempts' => $firsts->get($exam->id)['attempts'] ?? 0,
        ])->all();
    }

    /**
     * Xu hướng điểm trung bình lần đầu 8 tuần gần nhất, tuần cũ trái sang phải.
     * Mỗi cặp học sinh-đề chỉ tính một lần vào tuần nộp lần đầu.
     *
     * @return Collection<int, array{label: string, average: float|null}>
     */
    protected function weeklyTrend(int $subjectId): Collection
    {
        $firsts = ExamAttempt::query()
            ->where('subject_id', $subjectId)
            ->where('status', AttemptStatus::Graded->value)
            ->whereNotNull('submitted_at')
            ->get(['student_id', 'exam_id', 'attempt_no', 'score', 'max_score', 'submitted_at'])
            ->groupBy(fn (ExamAttempt $attempt): string => $attempt->student_id.'-'.$attempt->exam_id)
            ->map(function (Collection $rows): ?ExamAttempt {
                $first = $rows->sortBy('attempt_no')->first();
                $max = (float) $first->max_score;

                return $max > 0 ? $first : null;
            })
            ->filter();

        $out = collect();

        for ($i = 7; $i >= 0; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end = (clone $start)->endOfWeek();

            $week = $firsts->filter(fn (ExamAttempt $attempt): bool => $attempt->submitted_at !== null
                && $attempt->submitted_at->greaterThanOrEqualTo($start)
                && $attempt->submitted_at->lessThanOrEqualTo($end));

            $percents = $week->map(fn (ExamAttempt $attempt): float => round(((float) $attempt->score / (float) $attempt->max_score) * 100, 2));

            $out->push([
                'label' => $start->format('d/m'),
                'average' => $percents->isNotEmpty() ? round($percents->avg(), 1) : null,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array{completed: int, opened: int, pending: int}
     */
    protected function assignmentCompletion(array $memberIds): array
    {
        if ($memberIds === []) {
            return ['completed' => 0, 'opened' => 0, 'pending' => 0];
        }

        $receipts = AssignmentReceipt::query()->whereIn('user_id', $memberIds)->get(['opened_at', 'completed_at']);

        $completed = $receipts->whereNotNull('completed_at')->count();
        $opened = $receipts->whereNull('completed_at')->whereNotNull('opened_at')->count();

        return [
            'completed' => $completed,
            'opened' => $opened,
            'pending' => $receipts->count() - $completed - $opened,
        ];
    }

    /**
     * Số lượt nộp 14 ngày gần nhất, cũ trái sang phải.
     *
     * @return Collection<int, array{label: string, count: int}>
     */
    protected function dailySubmissions(int $subjectId): Collection
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = ExamAttempt::query()
            ->where('subject_id', $subjectId)
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Graded->value])
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $since)
            ->select(DB::raw('DATE(submitted_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $out = collect();

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $out->push([
                'label' => $date->format('d/m'),
                'count' => (int) ($counts->get($date->format('Y-m-d')) ?? 0),
            ]);
        }

        return $out;
    }
}
