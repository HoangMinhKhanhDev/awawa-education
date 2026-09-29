<?php

namespace App\Livewire;

use App\Models\Announcement;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\StudentAbility;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Thông tin')]
class Info extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $subject = app(SubjectContext::class)->subject();

        $leaderboard = collect();
        $announcements = collect();

        if ($subject !== null) {
            $leaderboard = $this->leaderboard();
            $announcements = Announcement::query()
                ->published()
                ->ordered()
                ->with('creator')
                ->limit(20)
                ->get();
        }

        return view('livewire.info', [
            'user' => $user,
            'subject' => $subject,
            'leaderboard' => $leaderboard,
            'announcements' => $announcements,
            'subjects' => $subject === null ? Subject::query()->orderBy('order')->get() : collect(),
        ]);
    }

    /**
     * Xếp hạng theo điểm thực lực (trung bình có trọng số các lần làm đầu),
     * kèm số đề, lần tốt nhất và tổng lượt làm.
     *
     * @return Collection<int, array{student: User|null, average: float, exams: int, best_percent: float, retakes: int}>
     */
    protected function leaderboard(): Collection
    {
        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->get();

        $abilities = app(StudentAbility::class)->rows()->keyBy('student_id');

        return $members
            ->map(function (TeamMembership $membership) use ($abilities): array {
                $row = $abilities->get($membership->student_id);

                return [
                    'student' => $membership->student,
                    'average' => $row['average'] ?? 0.0,
                    'exams' => $row['exams'] ?? 0,
                    'best_percent' => $row['best_percent'] ?? 0.0,
                    'retakes' => $row['retakes'] ?? 0,
                ];
            })
            // Sắp theo khoá ít quan trọng trước, khoá chính sau, để thứ tự trước
            // được giữ làm tiêu chí phụ (PHP 8 sắp xếp ổn định).
            ->sortByDesc('exams')
            ->sortByDesc('best_percent')
            ->sortByDesc('average')
            ->values();
    }
}
