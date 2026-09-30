<?php

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Services\Assignments\AssignmentManager;
use App\Services\GradingService;
use App\Support\SafeCache;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.focus')]
#[Title('Làm bài')]
class Take extends Component
{
    /**
     * Hai sự kiện cùng loại trong khoảng thời gian này sẽ bị gộp thành một.
     */
    protected const ANTI_CHEAT_DEDUPE_SECONDS = 60;

    #[Locked]
    public int $examId;

    #[Locked]
    public int $attemptId;

    public string $expiresAtIso = '';

    public int $violations = 0;

    /**
     * @var array<int|string, array{selected: int|null, text: string}>
     */
    public array $answers = [];

    public function mount(Exam $exam): void
    {
        $user = auth()->user();

        $this->examId = $exam->id;

        if ($exam->status !== ExamStatus::Published) {
            abort(403, 'Đề chưa được giao.');
        }

        if ($exam->starts_at !== null && $exam->starts_at->isFuture()) {
            abort(403, 'Chưa tới thời gian làm bài.');
        }

        if ($exam->ends_at !== null && $exam->ends_at->isPast()) {
            abort(403, 'Đã hết thời gian làm bài.');
        }

        if (! $user->isStudent() || ! $user->isActiveMemberOf($exam->subject_id)) {
            abort(403, 'Bạn chưa thuộc đội tuyển của môn này.');
        }

        $attempts = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->newestAttempt()
            ->get();

        $attempt = $attempts->firstWhere('status', AttemptStatus::InProgress);

        if ($attempt === null) {
            if (! $exam->canAttemptAgain($user)) {
                if ($exam->allowsRetake()) {
                    session()->flash('status', 'Bạn đã dùng hết lượt làm bài này.');
                }

                $this->redirect(route('student.result', $exam), navigate: true);

                return;
            }

            $attempt = ExamAttempt::create([
                'subject_id' => $exam->subject_id,
                'exam_id' => $exam->id,
                'student_id' => $user->id,
                'status' => AttemptStatus::InProgress,
                'attempt_no' => $attempts->count() + 1,
                'started_at' => now(),
                'expires_at' => $exam->duration_minutes
                    ? now()->addMinutes($exam->duration_minutes)
                    : $exam->ends_at,
                'max_score' => $exam->total_points,
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'anti_cheat' => [],
            ]);

            foreach ($exam->examQuestions as $examQuestion) {
                AttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $examQuestion->question_id,
                ]);
            }
        }

        $this->attemptId = $attempt->id;
        $this->expiresAtIso = $attempt->expires_at?->toIso8601String() ?? '';
        $this->violations = count($attempt->anti_cheat ?? []);
        $this->loadAnswers();
    }

    protected function loadAnswers(): void
    {
        $attempt = $this->resolveOwnAttempt();

        $stored = AttemptAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        foreach ($stored as $questionId => $answer) {
            $selected = $answer->selected_option_ids[0] ?? null;

            $subs = [];

            foreach ((array) ($answer->sub_answers ?? []) as $value) {
                $subs[] = QuestionType::normalizeTruthy((string) ($value ?? ''));
            }

            $this->answers[$questionId] = [
                'selected' => $selected !== null ? (int) $selected : null,
                'text' => (string) $answer->answer_text,
                'subs' => $subs,
            ];
        }
    }

    /** Memo trong request: loadAnswers/saveProgress/render chạy liền nhau. */
    protected ?Exam $memoExam = null;

    /** @var Collection<int, ExamQuestion>|null */
    protected ?Collection $memoQuestions = null;

    protected function memoExam(): Exam
    {
        return $this->memoExam ??= Exam::query()->findOrFail($this->examId);
    }

    /**
     * Toàn bộ câu hỏi của đề, cache 60s theo (đề, lần làm, phiên bản đề).
     * Thứ tự trộn ổn định theo attempt nên cache được; giáo viên sửa đề
     * (updated_at đổi) là key mới có hiệu lực ngay, key cũ tự hết hạn.
     *
     * @return Collection<int, ExamQuestion>
     */
    protected function memoQuestions(ExamAttempt $attempt): Collection
    {
        if ($this->memoQuestions !== null) {
            return $this->memoQuestions;
        }

        $exam = $this->memoExam();

        $version = $exam->updated_at?->timestamp ?? 0;

        // Vẫn cache Collection Eloquent vì view cần model đầy đủ, nhưng bọc
        // SafeCache: cache cũ ghi bởi class đã đổi sẽ được bỏ thay vì làm vỡ
        // trang làm bài.
        $examQuestions = SafeCache::remember(
            "take-questions:v1:{$exam->getKey()}:{$attempt->getKey()}:{$version}",
            60,
            fn (): Collection => $exam->examQuestions()->with(['question.options', 'section'])->get()
        );

        if ($exam->shuffle_questions) {
            $examQuestions = $examQuestions->sortBy(
                fn ($item) => crc32($attempt->id.'-q-'.$item->question_id),
            )->values();
        }

        return $this->memoQuestions = $examQuestions;
    }

    /**
     * Attempt mà client gửi lên phải thuộc về chính học sinh đang đăng nhập
     * và thuộc đúng đề đang mở. Mọi action/render đều đi qua đây để chặn IDOR
     * (sửa attemptId trên DevTools để ghi đè bài của bạn khác).
     */
    protected function resolveOwnAttempt(): ExamAttempt
    {
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        Gate::authorize('view', $attempt);

        abort_unless(
            $attempt->exam_id === $this->examId && $attempt->student_id === auth()->id(),
            403,
            'Bài làm không thuộc về bạn.'
        );

        return $attempt;
    }

    protected function ensureAttemptWritable(ExamAttempt $attempt, Exam $exam): bool
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            return false;
        }

        if ($attempt->isExpired()) {
            return false;
        }

        if ($exam->ends_at !== null && $exam->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, true>
     */
    protected function allowedQuestionIds(Exam $exam): array
    {
        return $exam->examQuestions()->pluck('question_id')->map(fn ($id): int => (int) $id)->flip()->map(fn (): bool => true)->all();
    }

    public function saveProgress(): void
    {
        $attempt = $this->resolveOwnAttempt();
        $exam = $this->memoExam();

        abort_unless($attempt->exam_id === $exam->id && $exam->id === $this->examId, 403);

        if (! $this->ensureAttemptWritable($attempt, $exam)) {
            return;
        }

        $allowed = $this->allowedQuestionIds($exam);

        $stored = AttemptAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->get(['question_id', 'selected_option_ids', 'answer_text', 'sub_answers'])
            ->keyBy('question_id');

        $changed = [];

        foreach ($this->answers as $questionId => $answer) {
            $questionId = (int) $questionId;

            if (! isset($allowed[$questionId])) {
                continue;
            }

            $selected = ! empty($answer['selected']) ? [(int) $answer['selected']] : [];
            $text = isset($answer['text']) && $answer['text'] !== '' ? mb_substr((string) $answer['text'], 0, 20000) : null;
            $subs = $this->normalizeSubs($answer['subs'] ?? null);

            $existing = $stored->get($questionId);

            // Chỉ ghi dòng thực sự khác, nếu không mỗi lần lưu đều tạo ra
            // N câu UPDATE rác trên điện thoại.
            if ($existing !== null
                && $existing->selected_option_ids === $selected
                && $existing->answer_text === $text
                && ($existing->sub_answers ?? []) === ($subs ?? [])) {
                continue;
            }

            $changed[$questionId] = [
                'selected_option_ids' => $selected,
                'answer_text' => $text,
                'sub_answers' => $subs,
            ];
        }

        if ($changed === []) {
            return;
        }

        $now = now();

        $rows = [];

        foreach ($changed as $questionId => $values) {
            // upsert() đi thẳng xuống query builder nên không qua cast của model,
            // phải tự encode cột json.
            $rows[] = [
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'selected_option_ids' => json_encode($values['selected_option_ids']),
                'answer_text' => $values['answer_text'],
                'sub_answers' => $values['sub_answers'] === null ? null : json_encode($values['sub_answers']),
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        AttemptAnswer::query()->upsert($rows, ['attempt_id', 'question_id'], ['selected_option_ids', 'answer_text', 'sub_answers', 'updated_at']);
    }

    /**
     * Chuẩn hoá đáp án 4 mệnh đề a–d về mảng 4 giá trị true/false/null.
     * Không có mệnh đề nào được chọn thì trả về null để cột gọn nhẹ.
     *
     * @return array<int, string|null>|null
     */
    protected function normalizeSubs(mixed $subs): ?array
    {
        if (! is_array($subs)) {
            return null;
        }

        $out = [];

        foreach (array_slice(array_values($subs), 0, 4) as $value) {
            $out[] = QuestionType::normalizeTruthy((string) ($value ?? ''));
        }

        if (array_filter($out, fn ($value): bool => $value !== null) === []) {
            return null;
        }

        return $out;
    }

    /**
     * Ghi sự kiện rời trang nhưng chặn false positive.
     *
     * Trên di động, blur/visibilitychange bắn liên tục khi bật bàn phím ảo, mở
     * picker native, chạm thanh địa chỉ... Vì vậy chỉ tính sự kiện đã đi qua
     * bộ lọc phía client (rời hẳn >= 2 giây) và bỏ qua event cùng loại lặp lại
     * trong vòng một phút. Bỏ re-render: chip cảnh báo cập nhật ở lần
     * render kế tiếp (autosave/tương tác), khỏi tải lại toàn bộ đề mỗi ping.
     */
    public function logAntiCheat(string $type): void
    {
        $this->skipRender();

        $type = mb_substr(trim($type), 0, 30);

        validator(
            ['type' => $type],
            ['type' => ['required', 'string', Rule::in(['tab_hidden', 'blur', 'visibility', 'fullscreen_exit', 'copy', 'paste', 'window_blur'])]]
        )->validate();

        $attempt = $this->resolveOwnAttempt();

        if ($attempt->status !== AttemptStatus::InProgress || $attempt->isExpired()) {
            return;
        }

        $events = $attempt->anti_cheat ?? [];
        $lastRecordedAt = $this->lastAntiCheatAt($events, $type);

        if ($lastRecordedAt !== null && $lastRecordedAt->diffInSeconds(now()) < self::ANTI_CHEAT_DEDUPE_SECONDS) {
            return;
        }

        $events[] = ['type' => $type, 'at' => now()->toIso8601String()];

        $attempt->forceFill(['anti_cheat' => $events])->save();

        $this->violations = count($events);
    }

    /**
     * Thời điểm gần nhất của một loại sự kiện cụ thể.
     *
     * @param  array<int, array{type: string, at: string}>  $events
     */
    protected function lastAntiCheatAt(array $events, string $type): ?Carbon
    {
        for ($index = count($events) - 1; $index >= 0; $index--) {
            $event = $events[$index] ?? null;

            if (($event['type'] ?? null) !== $type) {
                continue;
            }

            try {
                return Carbon::parse($event['at']);
            } catch (InvalidFormatException) {
                return null;
            }
        }

        return null;
    }

    public function submit(): void
    {
        $attempt = $this->resolveOwnAttempt();
        $exam = $this->memoExam();

        abort_unless($attempt->exam_id === $exam->id && $exam->id === $this->examId, 403);

        if (! $this->ensureAttemptWritable($attempt, $exam)) {
            $this->redirect(route('student.result', $attempt->exam_id), navigate: true);

            return;
        }

        $this->saveProgress();

        app(GradingService::class)->gradeAttempt($attempt);

        // Nộp bài là đã xong phần học sinh được giao.
        if ($attempt->exam !== null) {
            app(AssignmentManager::class)->syncExamProgress($attempt->exam);
        }

        session()->flash('status', 'Đã nộp bài thành công.');

        $this->redirect(route('student.result', $attempt->exam_id), navigate: true);
    }

    public function render(): View
    {
        $exam = $this->memoExam();
        $attempt = $this->resolveOwnAttempt();

        $examQuestions = $this->memoQuestions($attempt);

        return view('livewire.student.take', [
            'exam' => $exam,
            'attempt' => $attempt,
            'questionGroups' => $this->groupBySection($examQuestions),
            'optionOrder' => fn (Collection $options) => $exam->shuffle_options
                ? $options->sortBy(fn ($option) => crc32($attempt->id.'-o-'.$option->id))->values()
                : $options,
        ]);
    }

    /**
     * Nhóm câu theo phần để hiện đoạn thông tin chung (đúng/sai chùm 4).
     * Câu không thuộc phần nào gom vào nhóm cuối. Giữ đúng thứ tự đã trộn.
     *
     * @param  Collection<int, ExamQuestion>  $examQuestions
     * @return Collection<int, array{section: ExamSection|null, items: Collection<int, ExamQuestion>}>
     */
    protected function groupBySection(Collection $examQuestions): Collection
    {
        $groups = collect();
        $order = [];

        foreach ($examQuestions as $examQuestion) {
            $key = $examQuestion->exam_section_id ?? 0;

            if (! isset($order[$key])) {
                $order[$key] = count($order);
                $groups->push([
                    'section' => $examQuestion->section,
                    'items' => collect(),
                ]);
            }

            $groups[$order[$key]]['items']->push($examQuestion);
        }

        return $groups;
    }
}
