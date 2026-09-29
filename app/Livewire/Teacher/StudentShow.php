<?php

namespace App\Livewire\Teacher;

use App\Enums\AttemptStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\AssignmentReceipt;
use App\Models\ExamAttempt;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\StudentAbility;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Hồ sơ học tập')]
class StudentShow extends Component
{
    public int $studentId;

    public function mount(User $student): void
    {
        Gate::authorize('manageStudents', User::class);

        abort_unless($student->role === Role::Student, 404);
        abort_unless($student->subject_id !== null && $student->subject_id === app(SubjectContext::class)->id(), 404);
        abort_unless(TeamMembership::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $student->subject_id)
            ->where('status', MembershipStatus::Active->value)
            ->exists(), 404);

        $this->studentId = $student->id;
    }

    public function render(): View
    {
        $student = User::query()->findOrFail($this->studentId);

        $abilities = app(StudentAbility::class)->rows()->keyBy('student_id');
        $ability = $abilities->get($student->id);

        $attempts = ExamAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Graded->value])
            ->with('exam')
            ->orderByDesc('submitted_at')
            ->get();

        $receipts = AssignmentReceipt::query()
            ->where('user_id', $student->id)
            ->with('assignment.assignable')
            ->latest('delivered_at')
            ->limit(50)
            ->get();

        // Hạng trong đội theo điểm thực lực.
        $rank = null;

        if ($ability !== null) {
            $ordered = TeamMembership::query()
                ->active()
                ->pluck('student_id')
                ->map(fn (int $id): float => (float) ($abilities->get($id)['average'] ?? 0.0))
                ->sortDesc()
                ->values();

            $rank = $ordered->search($ability['average']) + 1;
        }

        return view('livewire.teacher.student-show', [
            'student' => $student,
            'ability' => $ability,
            'rank' => $rank,
            'attempts' => $attempts,
            'receipts' => $receipts,
        ]);
    }
}
