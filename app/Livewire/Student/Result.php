<?php

namespace App\Livewire\Student;

use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Kết quả')]
class Result extends Component
{
    public int $examId;

    public int $attemptId;

    /**
     * Số thứ tự lần làm muốn xem. Bỏ trống thì hiển thị lần mới nhất.
     */
    #[Url(as: 'lan')]
    public ?int $attemptNo = null;

    /** Memo danh sách lần làm: mount() và render() cũ query 2 lần. */
    protected ?Collection $memoAttempts = null;

    public function mount(Exam $exam): void
    {
        $this->examId = $exam->id;

        $attempts = $this->attemptsFor($exam);

        $attempt = $this->attemptNo !== null
            ? $attempts->firstWhere('attempt_no', $this->attemptNo)
            : $attempts->sortByDesc('attempt_no')->first();

        if ($attempt === null) {
            $this->redirect(route('student.take', $exam), navigate: true);

            return;
        }

        Gate::authorize('view', $attempt);

        $this->attemptId = $attempt->id;
    }

    /**
     * @return Collection<int, ExamAttempt>
     */
    protected function attemptsFor(Exam $exam): Collection
    {
        if ($this->memoAttempts !== null) {
            return $this->memoAttempts;
        }

        return $this->memoAttempts = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->finished()
            ->newestAttempt()
            ->get();
    }

    public function render(): View
    {
        $exam = Exam::query()->findOrFail($this->examId);
        $attempt = ExamAttempt::query()->findOrFail($this->attemptId);

        $attempts = $this->attemptsFor($exam);
        $user = auth()->user();

        Gate::authorize('view', $attempt);

        return view('livewire.student.result', [
            'exam' => $exam,
            'attempt' => $attempt,
            'attempts' => $attempts,
            'previousAttempt' => $attempts->firstWhere('attempt_no', $attempt->attempt_no - 1),
            'maxAttempts' => $exam->maxAttempts(),
            'remainingAttempts' => $user->isStudent() ? $exam->remainingAttemptsFor($user) : 0,
            'canRetake' => $user->isStudent() && $exam->allowsRetake() && $exam->canAttemptAgain($user),
            'examQuestions' => $exam->examQuestions()->with(['question.options'])->get(),
            'answers' => $attempt->answers()->with('question')->get()->keyBy('question_id'),
        ]);
    }
}
