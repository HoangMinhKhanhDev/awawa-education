<?php

namespace App\Livewire;

use App\Models\Announcement;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
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
     * Xếp hạng theo điểm phần trăm cao nhất, kèm tổng điểm và số bài đã làm.
     *
     * @return Collection<int, array{student: User|null, best_percent: float, total: float, attempts: int}>
     */
    protected function leaderboard(): Collection
    {
        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->get();

        $rows = ExamAttempt::query()
            ->finished()
            ->select('student_id')
            ->selectRaw('MAX(CASE WHEN max_score > 0 THEN score * 100.0 / max_score ELSE 0 END) as best_percent')
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('SUM(score) as total')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        return $members
            ->map(function (TeamMembership $membership) use ($rows): array {
                $row = $rows->get($membership->student_id);

                return [
                    'student' => $membership->student,
                    'best_percent' => $row ? (float) $row->best_percent : 0.0,
                    'total' => $row ? (float) $row->total : 0.0,
                    'attempts' => $row ? (int) $row->attempts : 0,
                ];
            })
            // Sắp theo khoá ít quan trọng trước, khoá chính sau, để thứ tự trước
            // được giữ làm tiêu chí phụ (PHP 8 sắp xếp ổn định).
            ->sortByDesc('attempts')
            ->sortByDesc('best_percent')
            ->values();
    }
}
