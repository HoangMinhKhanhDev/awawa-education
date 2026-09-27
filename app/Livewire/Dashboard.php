<?php

namespace App\Livewire;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\Assignments\AssignmentManager;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    /**
     * Mở nội dung được giao: ghi nhận đã xem rồi đưa học sinh tới nơi cần đến.
     */
    public function openAssignment(int $assignmentId): void
    {
        $assignment = $this->myAssignment($assignmentId);

        if ($assignment === null) {
            return;
        }

        app(AssignmentManager::class)->receiptFor($assignment, auth()->user())?->markOpened();

        $type = $assignment->type();
        $assignable = $assignment->assignable;

        if ($type === null || $assignable === null) {
            return;
        }

        $this->redirect($type->studentUrl($assignable), navigate: true);
    }

    /**
     * Học sinh tự báo đã xem xong, dùng cho nội dung không có vòng nộp bài.
     */
    public function markAssignmentDone(int $assignmentId): void
    {
        $assignment = $this->myAssignment($assignmentId);

        if ($assignment === null) {
            return;
        }

        app(AssignmentManager::class)->receiptFor($assignment, auth()->user())?->markCompleted();

        session()->flash('status', 'Đã ghi nhận bạn đã xem xong.');
    }

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
        // Chỉ cần lần làm mới nhất của mỗi đề (để biết đang làm / còn thi),
        // cộng với vài lần nộp gần đây. Trước đây nạp toàn bộ lịch sử của học
        // sinh vào RAM rồi flatten + sort trong PHP.
        $attemptsByExam = ExamAttempt::query()
            ->where('student_id', $user->id)
            ->with('exam')
            ->newestAttempt()
            ->get()
            ->groupBy('exam_id');

        $exams = Exam::query()
            ->where('status', ExamStatus::Published->value)
            ->when($user->subject_id !== null, fn ($query) => $query->where('subject_id', $user->subject_id))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->withCount('examQuestions')
            ->get();

        $inProgress = $exams->filter(
            fn (Exam $exam) => $this->attemptsOf($attemptsByExam, $exam)->first()?->status === AttemptStatus::InProgress,
        );

        $available = $exams
            ->reject(fn (Exam $exam) => $this->attemptsOf($attemptsByExam, $exam)
                ->contains(fn (ExamAttempt $attempt) => $attempt->status === AttemptStatus::InProgress))
            ->reject(fn (Exam $exam) => ! $exam->canAttemptAgain($user))
            ->map(fn (Exam $exam): array => [
                'exam' => $exam,
                'remaining' => $exam->remainingAttemptsFor($user),
            ])
            ->values();

        $recent = ExamAttempt::query()
            ->where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->with('exam')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return [
            'inProgress' => $inProgress,
            'available' => $available,
            'recent' => $recent,
            'assignments' => $this->activeAssignments($user),
            'documents' => $this->publicDocuments($user),
            'documentsAreOwnSubject' => $user->subject_id !== null,
        ];
    }

    /**
     * Nội dung đang được giao mà học sinh chưa xem xong.
     *
     * @return Collection<int, Assignment>
     */
    protected function activeAssignments(User $user): Collection
    {
        if ($user->subject_id === null) {
            return collect();
        }

        return Assignment::query()
            ->withoutSubjectScope()
            ->where('subject_id', $user->subject_id)
            ->whereNull('recalled_at')
            // Nạp sẵn receipt của chính học sinh này, nếu không receiptFor() sẽ
            // đấu một câu query cho từng bài được giao (N+1) mỗi lần mở trang.
            ->with(['assignable', 'receipts' => fn ($query) => $query->where('user_id', $user->id)])
            ->whereHas('receipts', fn ($query) => $query->where('user_id', $user->id))
            ->get()
            ->reject(fn (Assignment $assignment): bool => $assignment->receiptFor($user)?->isCompleted() ?? true)
            ->sortBy([
                fn (Assignment $assignment): int => $assignment->isOverdue() ? 0 : 1,
                fn (Assignment $assignment): int => $assignment->due_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->values();
    }

    /**
     * Chỉ lấy những lần giao thuộc về học sinh đang đăng nhập.
     */
    protected function myAssignment(int $assignmentId): ?Assignment
    {
        return Assignment::query()
            ->withoutSubjectScope()
            ->where('subject_id', auth()->user()?->subject_id)
            ->whereHas('receipts', fn ($query) => $query->where('user_id', auth()->id()))
            ->find($assignmentId);
    }

    /**
     * Các lần làm của một đề, mới nhất đứng đầu.
     *
     * @param  Collection<int, Collection<int, ExamAttempt>>  $attemptsByExam
     * @return Collection<int, ExamAttempt>
     */
    protected function attemptsOf(Collection $attemptsByExam, Exam $exam): Collection
    {
        return $attemptsByExam->get($exam->id, collect());
    }

    /**
     * Tài liệu công khai học sinh xem được.
     *
     * - Đã vào đội: tài liệu công khai của môn mình.
     * - Chưa có môn (tự đăng ký): tài liệu công khai của mọi môn, kèm nhãn môn.
     *
     * @return Collection<int, Document>
     */
    protected function publicDocuments(User $user): Collection
    {
        $query = Document::query()->where('is_public', true)->with(['subject', 'creator']);

        if ($user->subject_id === null) {
            $query->withoutSubjectScope();
        }

        return $query->orderByDesc('created_at')->limit(8)->get();
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
