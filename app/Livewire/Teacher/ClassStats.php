<?php

namespace App\Livewire\Teacher;

use App\Enums\AttemptStatus;
use App\Models\AssignmentReceipt;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\StudentAbility;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                'violations' => 0,
            ]);
        }

        $abilities = app(StudentAbility::class)->rows();

        $distribution = [
            ['label' => 'Giỏi (≥ 8)', 'count' => 0, 'color' => 'bg-success'],
            ['label' => 'Khá (6.5 – 8)', 'count' => 0, 'color' => 'bg-brand-600 dark:bg-brand-400'],
            ['label' => 'Trung bình (5 – 6.5)', 'count' => 0, 'color' => 'bg-warning dark:bg-amber-400'],
            ['label' => 'Yếu (< 5)', 'count' => 0, 'color' => 'bg-signal dark:bg-red-400'],
            ['label' => 'Chưa có bài', 'count' => 0, 'color' => 'bg-slate-300 dark:bg-slate-600'],
        ];

        $memberIds = TeamMembership::query()->active()->pluck('student_id')->all();
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

        $examStats = $this->examStats();
        $completion = $this->assignmentCompletion($memberIds);
        $daily = $this->dailySubmissions();

        $scored = $abilities->filter(fn (array $row): bool => $row['exams'] > 0);

        $violations = (int) ExamAttempt::query()
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Graded->value])
            ->get(['anti_cheat'])
            ->sum(fn (ExamAttempt $attempt): int => count($attempt->anti_cheat ?? []));

        return view('livewire.teacher.class-stats', [
            'subject' => $subject,
            'totalMembers' => count($memberIds),
            'classAverage' => $scored->isNotEmpty() ? round($scored->avg('average'), 1) : null,
            'distribution' => $distribution,
            'examStats' => $examStats,
            'completion' => $completion,
            'daily' => $daily,
            'violations' => $violations,
        ]);
    }

    /**
     * Điểm trung bình lần đầu từng đề + số lượt làm.
     *
     * @return Collection<int, array{title: string, weight: float, average: float|null, attempts: int}>
     */
    protected function examStats(): Collection
    {
        $exams = Exam::query()->orderBy('created_at')->get(['id', 'title', 'settings']);

        if ($exams->isEmpty()) {
            return collect();
        }

        $firsts = ExamAttempt::query()
            ->where('status', AttemptStatus::Graded->value)
            ->get(['exam_id', 'attempt_no', 'score', 'max_score'])
            ->groupBy('exam_id')
            ->map(function (Collection $rows): array {
                // Lần đầu của từng học sinh trong đề.
                $percents = $rows->groupBy('student_id')->map(function (Collection $studentRows): ?float {
                    $first = $studentRows->sortBy('attempt_no')->first();
                    $max = (float) $first->max_score;

                    return $max > 0 ? round(((float) $first->score / $max) * 100, 2) : null;
                })->filter(fn (?float $value): bool => $value !== null);

                return [
                    'average' => $percents->isNotEmpty() ? round($percents->avg(), 2) : null,
                    'attempts' => $rows->count(),
                ];
            });

        return $exams->map(fn (Exam $exam): array => [
            'title' => $exam->title,
            'weight' => $exam->weight(),
            'average' => $firsts->get($exam->id)['average'] ?? null,
            'attempts' => $firsts->get($exam->id)['attempts'] ?? 0,
        ]);
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
    protected function dailySubmissions(): Collection
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = ExamAttempt::query()
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
