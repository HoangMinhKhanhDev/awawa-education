<?php

namespace App\Livewire\Notebook;

use App\Models\Notebook;
use App\Models\User;
use App\Support\NotebookConfig;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chuyển / tạo / đổi tên / xoá notebook của giáo viên đang mở.
 */
class Manager extends Component
{
    #[Locked]
    public int $notebookId;

    public string $newTitle = '';

    public ?string $error = null;

    public bool $atLimit = false;

    public function mount(int $notebookId): void
    {
        $this->notebookId = $notebookId;

        $this->guard();
    }

    public function render(): View
    {
        $user = auth()->user();
        $notebooks = Notebook::forUser($user);
        $this->atLimit = $notebooks->count() >= NotebookConfig::maxNotebooksPerUser();

        return view('livewire.notebook.manager', [
            'notebooks' => $notebooks,
            'current' => $notebooks->firstWhere('id', $this->notebookId) ?? $notebooks->first(),
            'maxNotebooks' => NotebookConfig::maxNotebooksPerUser(),
        ]);
    }

    public function create(): void
    {
        $user = $this->user();
        $this->error = null;

        $this->validate([
            'newTitle' => ['nullable', 'string', 'max:120'],
        ], [
            'newTitle.max' => 'Tên notebook không được quá 120 ký tự.',
        ]);

        if (Notebook::query()->where('owner_id', $user->id)->count() >= NotebookConfig::maxNotebooksPerUser()) {
            $this->error = 'Bạn đã đạt giới hạn '.NotebookConfig::maxNotebooksPerUser().' notebook.';

            return;
        }

        $notebook = Notebook::createFor($user, $this->newTitle);
        $this->newTitle = '';

        $this->redirectRoute('studio.ai.notebook', ['notebookId' => $notebook->id], navigate: true);
    }

    public function rename(int $id, string $title): void
    {
        $notebook = $this->ownedNotebook($id);
        $title = trim($title);

        if ($notebook === null) {
            return;
        }

        if ($title === '') {
            $this->error = 'Tên notebook không được để trống.';

            return;
        }

        if (mb_strlen($title) > 120) {
            $this->error = 'Tên notebook không được quá 120 ký tự.';

            return;
        }

        $notebook->update(['title' => $title]);
        $this->error = null;
        session()->flash('notebook_status', 'Đã đổi tên notebook.');
    }

    public function delete(int $id): void
    {
        $notebook = $this->ownedNotebook($id);

        if ($notebook === null) {
            return;
        }

        if (! $notebook->canBeDeletedBy(auth()->user())) {
            $this->error = 'Không thể xoá notebook cuối cùng. Hãy tạo notebook khác trước.';

            return;
        }

        $remaining = Notebook::forUser(auth()->user())->reject(fn (Notebook $item): bool => $item->id === $id)->first();
        $notebook->delete();
        $this->error = null;

        if ($id === $this->notebookId) {
            $this->redirectRoute('studio.ai.notebook', ['notebookId' => $remaining?->id], navigate: true);

            return;
        }

        session()->flash('notebook_status', 'Đã xoá notebook.');
    }

    protected function ownedNotebook(int $id): ?Notebook
    {
        $notebook = Notebook::query()->find($id);

        abort_if($notebook === null, 404);
        abort_unless($notebook->isOwnedBy(auth()->user()), 403);

        return $notebook;
    }

    protected function guard(): void
    {
        abort_unless($this->ownedNotebook($this->notebookId) !== null, 404);
    }

    protected function user(): User
    {
        $user = auth()->user();

        abort_unless($user?->isTeacher(), 403);

        return $user;
    }
}
