<?php

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\GradingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.focus')]
#[Title('Làm bài')]
class Take extends Component
{
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

        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->first();

        if ($attempt !== null && $attempt->status !== AttemptStatus::InProgress) {
            $this->redirect(route('student.result', $exam), navigate: true);

            return;
        }

        if ($attempt === null) {
            $attempt = ExamAttempt::create([
                'subject_id' => $exam->subject_id,
                'exam_id' => $exam->id,
                'student_id' => $user->id,
                'status' => AttemptStatus::InProgress,
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

        foreach ($this->answers as $questionId => $answer) {
            AttemptAnswer::query()
                ->where('attempt_id', $attempt->id)
                ->where('question_id', (int) $questionId)
                ->update([
                    'selected_option_ids' => ! empty($answer['selected']) ? [(int) $answer['selected']] : [],
                    'answer_text' => $answer['text'] !== '' ? $answer['text'] : null,
                ]);
        }
    }

    public function logAntiCheat(string $type): void
    {
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        if ($attempt->status !== AttemptStatus::InProgress) {
            return;
        }

        $events = $attempt->anti_cheat ?? [];
        $events[] = ['type' => $type, 'at' => now()->toIso8601String()];

        $attempt->forceFill(['anti_cheat' => $events])->save();

        $this->violations = count($events);
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

        session()->flash('status', 'Đã nộp bài thành công.');

        $this->redirect(route('student.result', $attempt->exam_id), navigate: true);
    }

    public function render(): View
    {
        $exam = Exam::query()->findOrFail($this->examId);
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        $examQuestions = $exam->examQuestions()->with(['question.options'])->get();

        if ($exam->shuffle_questions) {
            $examQuestions = $examQuestions->sortBy(
                fn ($item) => crc32($attempt->id.'-q-'.$item->question_id),
            )->values();
        }

        return view('livewire.student.take', [
            'exam' => $exam,
            'attempt' => $attempt,
            'examQuestions' => $examQuestions,
            'optionOrder' => fn (Collection $options) => $exam->shuffle_options
                ? $options->sortBy(fn ($option) => crc32($attempt->id.'-o-'.$option->id))->values()
                : $options,
        ]);
    }
}
