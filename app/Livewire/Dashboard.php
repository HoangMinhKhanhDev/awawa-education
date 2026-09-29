<?php

namespace App\Livewire;

use App\Enums\AssignableType;
use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AttemptAnswer;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\Assignments\AssignmentManager;
use App\Services\StudentAbility;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    public string $docSearch = '';

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
            'roleCounts' => $user->isSuperAdmin() ? Cache::remember(
                'dashboard-roles',
                60,
                fn (): array => [
                    'teacher' => User::query()->where('role', Role::Teacher->value)->count(),
                    'student' => User::query()->where('role', Role::Student->value)->count(),
                    'subject' => Subject::query()->count(),
                ],
            ) : null,
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
                'dueSoon' => $exam->due_at !== null && $exam->due_at->diffInHours(now(), false) >= -48,
            ])
            ->values();

        $recent = ExamAttempt::query()
            ->where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->with('exam')
            ->orderByDesc('submitted_at')
            ->limit(3)
            ->get();

        $assignments = $this->activeAssignments($user);
        $assignmentCards = $this->assignmentCards($assignments, $user);
        $inProgressCards = $this->inProgressCards($inProgress);

        return [
            'inProgress' => $inProgress,
            'inProgressCards' => $inProgressCards,
            'available' => $available,
            'recent' => $recent,
            'assignments' => $assignments,
            'assignmentCards' => $assignmentCards,
            'nextUp' => $this->nextUp($assignmentCards, $inProgressCards, $available),
            'myStats' => $this->myStats($user),
            'documents' => $this->publicDocuments($user),
            'documentsAreOwnSubject' => $user->subject_id !== null,
        ];
    }

    /**
     * Mọi thứ Blade cần cho một dòng việc, tính một lần ở đây thay vì
     * `@php` trong vòng lặp. Nút chính duy nhất: đã mở nội dung không cần
     * nộp thì nút thành "Đã xem xong", còn lại là "Mở".
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array{assignment: Assignment, type: AssignableType|null, daysLeft: int|null, opened: bool, overdue: bool, action: string}>
     */
    protected function assignmentCards(Collection $assignments, User $user): Collection
    {
        return $assignments->map(function (Assignment $assignment) use ($user): array {
            $type = $assignment->type();
            $receipt = $assignment->receiptFor($user);
            $opened = $receipt?->isOpened() ?? false;

            return [
                'assignment' => $assignment,
                'type' => $type,
                'daysLeft' => $assignment->daysLeft(),
                'opened' => $opened,
                'overdue' => $assignment->isOverdue(),
                'action' => $opened && ! $type?->requiresSubmission() ? 'done' : 'open',
            ];
        })->values();
    }

    /**
     * @param  Collection<int, Exam>  $inProgress
     * @return Collection<int, array{exam: Exam, attempt: ExamAttempt|null, answered: int, total: int, percent: int, minutesLeft: int|null}>
     */
    protected function inProgressCards(Collection $inProgress): Collection
    {
        if ($inProgress->isEmpty()) {
            return collect();
        }

        $attempts = ExamAttempt::query()
            ->whereIn('exam_id', $inProgress->pluck('id')->all())
            ->where('student_id', auth()->id())
            ->where('status', AttemptStatus::InProgress->value)
            ->newestAttempt()
            ->get()
            ->keyBy('exam_id');

        $answeredByAttempt = AttemptAnswer::query()
            ->whereIn('attempt_id', $attempts->pluck('id')->all())
            ->get(['attempt_id', 'selected_option_ids', 'answer_text'])
            ->groupBy('attempt_id')
            ->map(fn (Collection $answers): int => $answers
                ->filter(fn ($answer): bool => ! empty($answer->selected_option_ids) || filled($answer->answer_text))
                ->count());

        return $inProgress->map(function (Exam $exam) use ($attempts, $answeredByAttempt): array {
            $attempt = $attempts->get($exam->id);
            $total = (int) $exam->exam_questions_count;
            $answered = $attempt !== null ? (int) ($answeredByAttempt->get($attempt->id, 0)) : 0;

            return [
                'exam' => $exam,
                'attempt' => $attempt,
                'answered' => $answered,
                'total' => $total,
                'percent' => $total > 0 ? (int) round($answered / $total * 100) : 0,
                'minutesLeft' => $attempt !== null && $attempt->expires_at !== null
                    ? max(0, (int) now()->diffInMinutes($attempt->expires_at, false))
                    : null,
            ];
        })
            ->sortBy(fn (array $card): int => $card['minutesLeft'] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Một việc gấp nhất cho khối hero: trễ hạn → sắp hết giờ → đang làm dở → bài mới.
     *
     * @param  Collection<int, array>  $assignmentCards
     * @param  Collection<int, array>  $inProgressCards
     * @param  Collection<int, array>  $available
     * @return array{kind: string, title: string, hint: string|null, overdue: bool}|null
     */
    protected function nextUp(Collection $assignmentCards, Collection $inProgressCards, Collection $available): ?array
    {
        $overdue = $assignmentCards->firstWhere('overdue', true);

        if ($overdue !== null) {
            return [
                'kind' => 'assignment',
                'id' => $overdue['assignment']->id,
                'title' => $overdue['assignment']->assignable?->title ?? '(Nội dung đã bị xoá)',
                'hint' => 'Trễ hạn, làm ngay',
                'overdue' => true,
            ];
        }

        $doing = $inProgressCards->first();

        if ($doing !== null) {
            return [
                'kind' => 'exam',
                'id' => $doing['exam']->id,
                'title' => $doing['exam']->title,
                'hint' => 'Đang làm dở'.($doing['minutesLeft'] !== null ? ' · còn '.$doing['minutesLeft'].' phút' : ''),
                'overdue' => false,
            ];
        }

        $todo = $assignmentCards->first();

        if ($todo !== null) {
            return [
                'kind' => 'assignment',
                'id' => $todo['assignment']->id,
                'title' => $todo['assignment']->assignable?->title ?? '(Nội dung đã bị xoá)',
                'hint' => $todo['daysLeft'] !== null && $todo['daysLeft'] >= 0 ? 'Còn '.$todo['daysLeft'].' ngày' : null,
                'overdue' => false,
            ];
        }

        $next = $available->first();

        if ($next !== null) {
            return [
                'kind' => 'available',
                'id' => $next['exam']->id,
                'title' => $next['exam']->title,
                'hint' => $next['exam']->exam_questions_count.' câu'.($next['remaining'] < $next['exam']->maxAttempts() ? ' · còn '.$next['remaining'].' lượt' : ''),
                'overdue' => false,
            ];
        }

        return null;
    }

    /**
     * @return array{average: float|null, exams: int, rank: int|null, members: int}
     */
    protected function myStats(User $user): array
    {
        if ($user->subject_id === null) {
            return ['average' => null, 'exams' => 0, 'rank' => null, 'members' => 0];
        }

        $abilities = app(StudentAbility::class)->rows();

        $mine = $abilities->firstWhere('student_id', $user->id);

        if ($mine === null || $mine['exams'] === 0) {
            return [
                'average' => null,
                'exams' => 0,
                'rank' => null,
                'members' => TeamMembership::query()->active()->count(),
            ];
        }

        $ordered = $abilities
            ->sortByDesc('exams')
            ->sortByDesc('best_percent')
            ->sortByDesc('average')
            ->values();

        $rank = $ordered->search(fn (array $row): bool => $row['student_id'] === $user->id);

        return [
            'average' => $mine['average'],
            'exams' => $mine['exams'],
            'rank' => $rank === false ? null : $rank + 1,
            'members' => TeamMembership::query()->active()->count(),
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
            ->orderByDesc('assigned_at')
            ->limit(20)
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

        $search = trim($this->docSearch);

        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return $query->orderByDesc('created_at')->limit(8)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherData(): array
    {
        // 3 COUNT mỗi lần mở dashboard. Cache 60s theo môn để nhiều giáo viên
        // cùng môn dùng chung, hết 1 phút mới đếm lại.
        $subjectId = app(SubjectContext::class)->id();

        return Cache::remember(
            'dashboard-teacher:'.$subjectId,
            60,
            fn (): array => [
                'members' => TeamMembership::query()->active()->count(),
                'pendingGrading' => ExamAttempt::query()->where('status', AttemptStatus::Submitted->value)->count(),
                'published' => Exam::query()->where('status', ExamStatus::Published->value)->count(),
            ],
        );
    }
}
