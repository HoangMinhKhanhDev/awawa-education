<?php

namespace App\Livewire\Notebook;

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Notebook')]
class Workspace extends Component
{
    public int $notebookId;

    public string $mobileTab = 'chat';

    public function mount(?int $notebookId = null): void
    {
        $user = auth()->user();

        abort_unless($user?->isTeacher(), 403);

        $this->notebookId = $notebookId !== null
            ? $this->resolveNotebook($user, $notebookId)->id
            : Notebook::defaultFor($user)->id;
    }

    protected function resolveNotebook(User $user, int $notebookId): Notebook
    {
        $notebook = Notebook::query()->findOrFail($notebookId);

        abort_unless($notebook->isOwnedBy($user), 404);

        return $notebook;
    }

    public function render(): View
    {
        $notebook = Notebook::query()->with('subject')->findOrFail($this->notebookId);

        return view('livewire.notebook.workspace', [
            'notebook' => $notebook,
        ])->layout('components.layouts.app', ['fullBleed' => true]);
    }
}
