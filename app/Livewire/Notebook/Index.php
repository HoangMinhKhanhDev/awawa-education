<?php

namespace App\Livewire\Notebook;

use App\Models\Notebook;
use App\Models\User;
use App\Support\NotebookConfig;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Trang Sổ tay AI: liệt kê, tạo, đổi tên và xoá notebook của giáo viên.
 *
 * Đây là cửa vào duy nhất của workspace — workspace chỉ còn nút quay lại về
 * đây, không chuyển/tạo notebook tại chỗ nữa.
 */
#[Layout('components.layouts.app')]
#[Title('Sổ tay AI')]
class Index extends Component
{
    public string $newTitle = '';

    public bool $creating = false;

    public ?int $renamingId = null;

    public string $renamingTitle = '';

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isTeacher(), 403);
    }

    public function render(): View
    {
        $notebooks = Notebook::forUser(auth()->user())->loadMissing('subject');
        $count = $notebooks->count();
        $limit = NotebookConfig::maxNotebooksPerUser();

        return view('livewire.notebook.index', [
            'notebooks' => $notebooks,
            'resume' => $notebooks->sortByDesc('updated_at')->first(),
            'atLimit' => $count >= $limit,
            'maxNotebooks' => $limit,
            'deletable' => $count > 1,
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
        $this->creating = false;

        $this->redirectRoute('studio.ai.notebook', ['notebookId' => $notebook->id], navigate: true);
    }

    public function startRename(int $id): void
    {
        $notebook = $this->ownedNotebook($id);

        if ($notebook === null) {
            return;
        }

        $this->renamingId = $notebook->id;
        $this->renamingTitle = $notebook->title;
        $this->error = null;
    }

    public function saveRename(): void
    {
        $notebook = $this->ownedNotebook($this->renamingId ?? 0);

        if ($notebook === null) {
            return;
        }

        $this->validate(['renamingTitle' => ['required', 'string', 'max:120']], [
            'renamingTitle.required' => 'Tên notebook không được để trống.',
            'renamingTitle.max' => 'Tên notebook không được quá 120 ký tự.',
        ]);

        $notebook->update(['title' => trim($this->renamingTitle)]);
        $this->renamingId = null;
        $this->renamingTitle = '';
        $this->error = null;
    }

    public function cancelRename(): void
    {
        $this->renamingId = null;
        $this->renamingTitle = '';
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

        $notebook->delete();
        $this->error = null;

        session()->flash('notebook_status', 'Đã xoá notebook.');
    }

    protected function ownedNotebook(int $id): ?Notebook
    {
        $notebook = Notebook::query()->find($id);

        abort_if($notebook === null, 404);
        abort_unless($notebook->isOwnedBy(auth()->user()), 403);

        return $notebook;
    }

    protected function user(): User
    {
        $user = auth()->user();

        abort_unless($user?->isTeacher(), 403);

        return $user;
    }
}
