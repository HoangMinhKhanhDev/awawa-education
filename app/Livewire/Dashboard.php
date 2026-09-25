<?php

namespace App\Livewire;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\Role;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $context = app(SubjectContext::class);

        return view('livewire.dashboard', [
            'user' => $user,
            'currentSubject' => $context->subject(),
            'roleCounts' => $user->isSuperAdmin() ? [
                'teacher' => User::query()->where('role', Role::Teacher->value)->count(),
                'student' => User::query()->where('role', Role::Student->value)->count(),
                'subject' => Subject::query()->count(),
            ] : null,
            'studentData' => $user->isStudent() ? $this->studentData($user) : null,
            'teacherData' => $user->isTeacher() ? $this->teacherData() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentData(User $user): array
    {
        $attempts = ExamAttempt::query()
            ->where('student_id', $user->id)
            ->with('exam')
            ->get()
            ->keyBy('exam_id');

        $exams = Exam::query()
            ->where('status', ExamStatus::Published->value)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->withCount('examQuestions')
            ->get();

        $inProgress = $exams->filter(fn (Exam $exam) => ($attempts->get($exam->id)?->status ?? null) === AttemptStatus::InProgress);

        $available = $exams
            ->reject(fn (Exam $exam) => $attempts->has($exam->id) && $attempts->get($exam->id)->status->isFinished())
            ->reject(fn (Exam $exam) => ($attempts->get($exam->id)?->status ?? null) === AttemptStatus::InProgress)
            ->values();

        $recent = $attempts
            ->filter(fn (ExamAttempt $attempt) => $attempt->status->isFinished())
            ->sortByDesc('submitted_at')
            ->take(5)
            ->values();

        return [
            'inProgress' => $inProgress,
            'available' => $available,
            'recent' => $recent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherData(): array
    {
        return [
            'members' => TeamMembership::query()->active()->count(),
            'pendingGrading' => ExamAttempt::query()->where('status', AttemptStatus::Submitted->value)->count(),
            'published' => Exam::query()->where('status', ExamStatus::Published->value)->count(),
        ];
    }
}
