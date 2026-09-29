<?php

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Services\Assignments\AssignmentManager;
use App\Services\GradingService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
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

    public int $examId;

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
        $stored = AttemptAnswer::query()
            ->where('attempt_id', $this->attemptId)
            ->get()
            ->keyBy('question_id');

        foreach ($stored as $questionId => $answer) {
            $selected = $answer->selected_option_ids[0] ?? null;

            $this->answers[$questionId] = [
                'selected' => $selected !== null ? (int) $selected : null,
                'text' => (string) $answer->answer_text,
            ];
        }
    }

    public function saveProgress(): void
    {
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        if ($attempt->status !== AttemptStatus::InProgress) {
            return;
        }

        $stored = AttemptAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->get(['question_id', 'selected_option_ids', 'answer_text'])
            ->keyBy('question_id');

        $changed = [];

        foreach ($this->answers as $questionId => $answer) {
            $questionId = (int) $questionId;
            $selected = ! empty($answer['selected']) ? [(int) $answer['selected']] : [];
            $text = $answer['text'] !== '' ? $answer['text'] : null;

            $existing = $stored->get($questionId);

            // Chỉ ghi dòng thực sự khác, nếu không mỗi lần lưu đều tạo ra
            // N câu UPDATE rác trên điện thoại.
            if ($existing !== null
                && $existing->selected_option_ids === $selected
                && $existing->answer_text === $text) {
                continue;
            }

            $changed[$questionId] = ['selected_option_ids' => $selected, 'answer_text' => $text];
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
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        AttemptAnswer::query()->upsert($rows, ['attempt_id', 'question_id'], ['selected_option_ids', 'answer_text', 'updated_at']);
    }

    /**
     * Ghi sự kiện rời trang nhưng chặn false positive.
     *
     * Trên di động, blur/visibilitychange bắn liên tục khi bật bàn phím ảo, mở
     * picker native, chạm thanh địa chỉ... Vì vậy chỉ tính sự kiện đã đi qua
     * bộ lọc phía client (rời hẳn >= 2 giây) và bỏ qua event cùng loại lặp lại
     * trong vòng một phút. Cố tình không skipRender: render() chỉ đọc attempt
     * và câu hỏi, không truy vấn lại đề.
     */
    public function logAntiCheat(string $type): void
    {
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        if ($attempt->status !== AttemptStatus::InProgress) {
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
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        if ($attempt->status !== AttemptStatus::InProgress) {
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
        $exam = Exam::query()->findOrFail($this->examId);
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        $examQuestions = $exam->examQuestions()->with(['question.options', 'section'])->get();

        if ($exam->shuffle_questions) {
            $examQuestions = $examQuestions->sortBy(
                fn ($item) => crc32($attempt->id.'-q-'.$item->question_id),
            )->values();
        }

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
