<?php

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Kết quả')]
class Result extends Component
{
    public int $examId;

    public int $attemptId;

    public function mount(Exam $exam): void
    {
        $this->examId = $exam->id;

        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->first();

        if ($attempt === null) {
            $this->redirect(route('student.take', $exam), navigate: true);

            return;
        }

        if ($attempt->status === AttemptStatus::InProgress) {
            $this->redirect(route('student.take', $exam), navigate: true);

            return;
        }

        Gate::authorize('view', $attempt);

        $this->attemptId = $attempt->id;
    }

    public function render(): View
    {
        $exam = Exam::query()->findOrFail($this->examId);
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        $examQuestions = $exam->examQuestions()->with(['question.options'])->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return view('livewire.student.result', [
            'exam' => $exam,
            'attempt' => $attempt,
            'examQuestions' => $examQuestions,
            'answers' => $answers,
        ]);
    }
}
