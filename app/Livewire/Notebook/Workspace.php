<?php

namespace App\Livewire\Notebook;

use App\Models\Notebook;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Notebook')]
class Workspace extends Component
{
    public int $notebookId;

    public string $mobileTab = 'chat';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isTeacher(), 403);

        $this->notebookId = Notebook::forOwner(auth()->user())->id;
    }

    public function render(): View
    {
        $notebook = Notebook::query()->with('subject')->findOrFail($this->notebookId);

        return view('livewire.notebook.workspace', [
            'notebook' => $notebook,
        ]);
    }
}
