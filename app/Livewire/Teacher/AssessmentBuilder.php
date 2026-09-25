<?php

namespace App\Livewire\Teacher;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\SubjectFeature;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Services\NotificationDispatcher;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AssessmentBuilder extends Component
{
    public int $examId;

    public string $title = '';

    public string $description = '';

    public ?int $durationMinutes = null;

    public bool $shuffleQuestions = false;

    public bool $shuffleOptions = false;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $dueAt = '';

    public string $newSectionTitle = '';

    /**
     * @var array<int|string, string>
     */
    public array $sectionTitles = [];

    public string $questionSearch = '';

    /**
     * @var array<int, int|string>
     */
    public array $selectedQuestions = [];

    public ?int $pickSectionId = null;

    public function mount(Exam $exam): void
    {
        Gate::authorize('update', $exam);

        $user = auth()->user();
        $subject = app(SubjectContext::class)->subject();

        $requiredFeature = $exam->type === ExamType::Exam
            ? SubjectFeature::Exams
            : SubjectFeature::Assignments;

        if (! $user->isSuperAdmin() && ($subject === null || ! $subject->hasFeature($requiredFeature))) {
            abort(403, 'Tính năng này chưa được bật cho môn của bạn.');
        }

        $this->examId = $exam->id;
        $this->loadMeta();
        $this->loadSections();

        $this->pickSectionId = $exam->sections()->value('id');
    }

    protected function exam(): Exam
    {
        return Exam::query()->findOrFail($this->examId);
    }

    protected function loadMeta(): void
    {
        $exam = $this->exam();

        $this->title = $exam->title;
        $this->description = (string) $exam->description;
        $this->durationMinutes = $exam->duration_minutes;
        $this->shuffleQuestions = $exam->shuffle_questions;
        $this->shuffleOptions = $exam->shuffle_options;
        $this->startsAt = $exam->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $exam->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->dueAt = $exam->due_at?->format('Y-m-d\TH:i') ?? '';
    }

    protected function loadSections(): void
    {
        $this->sectionTitles = $this->exam()->sections->pluck('title', 'id')->all();
    }

    public function saveMeta(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'durationMinutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'dueAt' => ['nullable', 'date'],
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'endsAt.after_or_equal' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
        ]);

        $exam->update([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'duration_minutes' => $this->durationMinutes,
            'shuffle_questions' => $this->shuffleQuestions,
            'shuffle_options' => $this->shuffleOptions,
            'starts_at' => $this->startsAt ?: null,
            'ends_at' => $this->endsAt ?: null,
            'due_at' => $this->dueAt ?: null,
        ]);

        session()->flash('status', 'Đã lưu thông tin.');
    }

    public function updatedSectionTitles(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        foreach ($this->sectionTitles as $id => $title) {
            ExamSection::query()
                ->where('exam_id', $exam->id)
                ->whereKey($id)
                ->update(['title' => trim((string) $title) ?: 'Phần']);
        }
    }

    public function addSection(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $order = (int) $exam->sections()->max('order') + 1;

        ExamSection::create([
            'exam_id' => $exam->id,
            'title' => trim($this->newSectionTitle) ?: 'Phần mới',
            'order' => $order,
        ]);

        $this->newSectionTitle = '';
        $this->loadSections();
    }

    public function deleteSection(int $id): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamSection::query()->where('exam_id', $exam->id)->whereKey($id)->delete();

        if ($this->pickSectionId === $id) {
            $this->pickSectionId = null;
        }

        $this->loadSections();
    }

    public function moveSection(int $id, string $direction): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $sections = $exam->sections()->orderBy('order')->get()->values();
        $index = $sections->search(fn (ExamSection $section) => $section->id === $id);

        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($sections[$targetIndex])) {
            return;
        }

        $current = $sections[$index];
        $target = $sections[$targetIndex];

        $currentOrder = $current->order;
        $current->update(['order' => $target->order]);
        $target->update(['order' => $currentOrder]);

        $this->loadSections();
    }

    public function addQuestions(): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedQuestions))));

        if ($ids === []) {
            return;
        }

        $order = (int) $exam->examQuestions()->max('order');

        $added = 0;

        foreach ($ids as $questionId) {
            $question = Question::query()->whereKey($questionId)->first();

            if ($question === null) {
                continue;
            }

            $examQuestion = ExamQuestion::query()->firstOrCreate(
                ['exam_id' => $exam->id, 'question_id' => $question->id],
                ['exam_section_id' => $this->pickSectionId, 'order' => ++$order],
            );

            if ($examQuestion->wasRecentlyCreated) {
                $added++;
            }
        }

        $exam->refreshTotalPoints();
        $this->selectedQuestions = [];

        session()->flash('status', $added > 0 ? "Đã thêm {$added} câu hỏi." : 'Các câu hỏi này đã có trong đề.');
    }

    public function removeQuestion(int $examQuestionId): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamQuestion::query()->where('exam_id', $exam->id)->whereKey($examQuestionId)->delete();

        $exam->refreshTotalPoints();

        session()->flash('status', 'Đã gỡ câu hỏi khỏi đề.');
    }

    public function moveQuestion(int $examQuestionId, string $direction): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        $examQuestions = $exam->examQuestions()->orderBy('order')->get()->values();
        $index = $examQuestions->search(fn (ExamQuestion $item) => $item->id === $examQuestionId);

        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($examQuestions[$targetIndex])) {
            return;
        }

        $current = $examQuestions[$index];
        $target = $examQuestions[$targetIndex];

        $currentOrder = $current->order;
        $current->update(['order' => $target->order]);
        $target->update(['order' => $currentOrder]);
    }

    public function setQuestionPoints(int $examQuestionId, ?float $points): void
    {
        $exam = $this->exam();
        Gate::authorize('update', $exam);

        ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->whereKey($examQuestionId)
            ->update(['points' => $points]);

        $exam->refreshTotalPoints();
    }

    public function changeStatus(string $status): void
    {
        $exam = $this->exam();
        Gate::authorize('publish', $exam);

        $target = ExamStatus::from($status);

        if ($target === ExamStatus::Published && $exam->examQuestions()->count() === 0) {
            session()->flash('error', 'Cần thêm ít nhất một câu hỏi trước khi giao.');

            return;
        }

        $exam->forceFill(['status' => $target])->save();

        if ($target === ExamStatus::Published) {
            app(NotificationDispatcher::class)->examPublished($exam);
        }

        session()->flash('status', 'Đã cập nhật trạng thái.');
    }

    public function render(): View
    {
        $exam = $this->exam();

        return view('livewire.teacher.assessment-builder', [
            'exam' => $exam,
            'sections' => $exam->sections()->get(),
            'examQuestions' => $exam->examQuestions()->with(['question', 'section'])->get(),
            'bankQuestions' => Question::query()
                ->active()
                ->when($this->questionSearch !== '', fn ($query) => $query->where('content', 'like', '%'.$this->questionSearch.'%'))
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(),
            'type' => $exam->type,
        ]);
    }
}
