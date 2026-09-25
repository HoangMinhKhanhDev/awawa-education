<?php

namespace App\Livewire\Teacher;

use App\Enums\AttemptStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\SubjectFeature;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class GradingIndex extends Component
{
    public int $examId;

    public ?int $gradingAttemptId = null;

    /**
     * @var array<int|string, array{points: float|string|null, feedback: string}>
     */
    public array $manual = [];

    public function mount(Exam $exam): void
    {
        Gate::authorize('view', $exam);

        $user = auth()->user();
        $subject = app(SubjectContext::class)->subject();

        $requiredFeature = $exam->type === ExamType::Exam
            ? SubjectFeature::Exams
            : SubjectFeature::Assignments;

        if (! $user->isSuperAdmin() && ($subject === null || ! $subject->hasFeature($requiredFeature))) {
            abort(403, 'Tính năng này chưa được bật cho môn của bạn.');
        }

        $this->examId = $exam->id;
    }

    public function openGrading(int $attemptId): void
    {
        $attempt = ExamAttempt::query()->findOrFail($attemptId);
        Gate::authorize('grade', $attempt);

        $this->gradingAttemptId = $attemptId;
        $this->manual = [];

        foreach ($attempt->answers()->with('question')->get() as $answer) {
            if ($answer->question?->type === QuestionType::Essay) {
                $this->manual[$answer->id] = [
                    'points' => $answer->awarded_points !== null ? (float) $answer->awarded_points : null,
                    'feedback' => (string) $answer->feedback,
                ];
            }
        }
    }

    public function closeGrading(): void
    {
        $this->gradingAttemptId = null;
        $this->manual = [];
    }

    public function saveGrading(): void
    {
        $attempt = ExamAttempt::query()->findOrFail($this->gradingAttemptId);
        Gate::authorize('grade', $attempt);

        foreach ($this->manual as $answerId => $data) {
            $answer = $attempt->answers()->whereKey($answerId)->with('question')->first();

            if ($answer === null) {
                continue;
            }

            $max = (float) ($answer->question?->points ?? 0);
            $points = $data['points'] === null || $data['points'] === ''
                ? null
                : max(0, min((float) $data['points'], $max));

            $answer->forceFill([
                'awarded_points' => $points,
                'feedback' => filled($data['feedback']) ? $data['feedback'] : null,
            ])->save();
        }

        $attempt->recomputeScore();

        if ($attempt->hasPendingManualGrading()) {
            $attempt->forceFill(['status' => AttemptStatus::Submitted])->save();
        } else {
            $attempt->forceFill([
                'status' => AttemptStatus::Graded,
                'graded_at' => now(),
                'graded_by' => auth()->id(),
            ])->save();
        }

        $this->gradingAttemptId = null;
        $this->manual = [];

        session()->flash('status', 'Đã lưu điểm và nhận xét.');
    }

    public function render(): View
    {
        $exam = Exam::query()->findOrFail($this->examId);

        $gradingAttempt = $this->gradingAttemptId !== null
            ? ExamAttempt::query()
                ->with(['student', 'answers.question.options'])
                ->findOrFail($this->gradingAttemptId)
            : null;

        return view('livewire.teacher.grading-index', [
            'exam' => $exam,
            'attempts' => ExamAttempt::query()
                ->with('student')
                ->where('exam_id', $exam->id)
                ->orderByDesc('submitted_at')
                ->get(),
            'gradingAttempt' => $gradingAttempt,
        ]);
    }
}
