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
use Illuminate\Support\Facades\DB;
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
     * @return Collection<int, array{student: User|null, total: float, attempts: int}>
     */
    protected function leaderboard(): Collection
    {
        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->get();

        $totals = ExamAttempt::query()
            ->finished()
            ->select('student_id', DB::raw('SUM(score) as total'), DB::raw('COUNT(*) as attempts'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        return $members
            ->map(function (TeamMembership $membership) use ($totals): array {
                $row = $totals->get($membership->student_id);

                return [
                    'student' => $membership->student,
                    'total' => $row ? (float) $row->total : 0.0,
                    'attempts' => $row ? (int) $row->attempts : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
