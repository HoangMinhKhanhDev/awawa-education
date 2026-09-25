<?php

namespace App\Livewire\Teacher\Concerns;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Services\NotificationDispatcher;
use App\Support\SubjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

trait ManagesAssessments
{
    public bool $showForm = false;

    public string $title = '';

    public string $description = '';

    abstract protected function assessmentType(): ExamType;

    protected function assessmentsQuery(): Builder
    {
        return Exam::query()
            ->ofType($this->assessmentType())
            ->withCount('examQuestions');
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Exam::class);

        $this->resetListForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetListForm();
    }

    public function create(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'title.min' => 'Tiêu đề quá ngắn.',
        ]);

        Gate::authorize('create', Exam::class);

        $exam = Exam::create([
            'subject_id' => app(SubjectContext::class)->id() ?? auth()->user()?->subject_id,
            'type' => $this->assessmentType(),
            'title' => $this->title,
            'description' => $this->description ?: null,
            'status' => ExamStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $this->showForm = false;
        $this->resetListForm();

        $this->redirect(route('studio.builder', $exam), navigate: true);
    }

    public function publish(int $id): void
    {
        $exam = Exam::query()->findOrFail($id);
        Gate::authorize('publish', $exam);

        if ($exam->examQuestions()->count() === 0) {
            session()->flash('error', 'Cần thêm ít nhất một câu hỏi trước khi giao.');

            return;
        }

        $exam->forceFill(['status' => ExamStatus::Published])->save();

        app(NotificationDispatcher::class)->examPublished($exam);

        session()->flash('status', 'Đã giao cho học sinh trong đội tuyển.');
    }

    public function close(int $id): void
    {
        $exam = Exam::query()->findOrFail($id);
        Gate::authorize('update', $exam);

        $exam->forceFill(['status' => ExamStatus::Closed])->save();

        session()->flash('status', 'Đã đóng.');
    }

    public function reopen(int $id): void
    {
        $exam = Exam::query()->findOrFail($id);
        Gate::authorize('update', $exam);

        $exam->forceFill(['status' => ExamStatus::Draft])->save();

        session()->flash('status', 'Đã chuyển về bản nháp.');
    }

    public function delete(int $id): void
    {
        $exam = Exam::query()->findOrFail($id);
        Gate::authorize('delete', $exam);

        $exam->delete();

        session()->flash('status', 'Đã xóa.');
    }

    protected function resetListForm(): void
    {
        $this->title = '';
        $this->description = '';
        $this->resetErrorBag();
    }
}
