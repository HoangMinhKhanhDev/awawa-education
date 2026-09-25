<?php

namespace App\Livewire\Teacher;

use App\Enums\SubjectFeature;
use App\Models\Announcement;
use App\Services\NotificationDispatcher;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Thông báo')]
class AnnouncementsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public bool $isPinned = false;

    public bool $publishNow = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', Announcement::class);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Announcement::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $announcement = Announcement::query()->findOrFail($id);
        Gate::authorize('update', $announcement);

        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->isPinned = $announcement->is_pinned;
        $this->publishNow = $announcement->isPublished();
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'isPinned' => ['boolean'],
            'publishNow' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề thông báo.',
            'body.required' => 'Vui lòng nhập nội dung thông báo.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'title' => $this->title,
            'body' => $this->body,
            'is_pinned' => $this->isPinned,
            'published_at' => $this->publishNow ? now() : null,
        ];

        $wasPublished = false;

        if ($this->editingId !== null) {
            $announcement = Announcement::query()->findOrFail($this->editingId);
            Gate::authorize('update', $announcement);

            $wasPublished = $announcement->isPublished();
            $announcement->update($attributes);
        } else {
            Gate::authorize('create', Announcement::class);

            $announcement = Announcement::create([
                'subject_id' => app(SubjectContext::class)->id() ?? auth()->user()?->subject_id,
                'created_by' => auth()->id(),
                ...$attributes,
            ]);
        }

        if ($announcement->isPublished() && ! $wasPublished) {
            app(NotificationDispatcher::class)->announcementPublished($announcement);
        }

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã lưu thông báo.');
    }

    public function togglePublish(int $id): void
    {
        $announcement = Announcement::query()->findOrFail($id);
        Gate::authorize('update', $announcement);

        $announcement->forceFill([
            'published_at' => $announcement->isPublished() ? null : now(),
        ])->save();

        session()->flash('status', 'Đã cập nhật trạng thái thông báo.');
    }

    public function delete(int $id): void
    {
        $announcement = Announcement::query()->findOrFail($id);
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        session()->flash('status', 'Đã xóa thông báo.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->body = '';
        $this->isPinned = false;
        $this->publishNow = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        return view('livewire.teacher.announcements-index', [
            'subject' => $subject,
            'announcements' => Announcement::query()->ordered()->with('creator')->get(),
            'featureEnabled' => $subject?->hasFeature(SubjectFeature::Announcements) ?? false,
        ]);
    }
}
