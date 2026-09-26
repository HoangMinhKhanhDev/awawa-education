<?php

namespace App\Livewire\Notebook;

use App\Models\Document;
use App\Models\Notebook;
use App\Models\NotebookSource;
use App\Services\Notebook\SourceIngestor;
use App\Support\NotebookConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Sources extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $notebookId;

    public string $addType = '';

    public string $title = '';

    public string $text = '';

    public $file = null;

    public ?int $documentId = null;

    public ?int $viewingSourceId = null;

    public ?string $error = null;

    public function mount(int $notebookId): void
    {
        $this->notebookId = $notebookId;
        $this->guard();
    }

    protected function notebook(): Notebook
    {
        return Notebook::query()->findOrFail($this->notebookId);
    }

    protected function guard(): void
    {
        abort_unless($this->notebook()->isOwnedBy(auth()->user()), 403);
    }

    public function updatedFile(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:'.NotebookConfig::maxFileBytes(), 'mimes:pdf,docx,txt,md,csv'],
        ], [
            'file.max' => 'Tệp tối đa '.config('awawa.notebook.max_file_mb').'MB.',
            'file.mimes' => 'Chỉ hỗ trợ PDF, DOCX, TXT, MD.',
        ]);
    }

    public function addFile(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'file' => ['required', 'file', 'max:'.NotebookConfig::maxFileBytes(), 'mimes:pdf,docx,txt,md,csv'],
        ], [
            'file.required' => 'Chọn một tệp để tải lên.',
            'file.max' => 'Tệp tối đa '.config('awawa.notebook.max_file_mb').'MB.',
            'file.mimes' => 'Chỉ hỗ trợ PDF, DOCX, TXT, MD.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $source = $ingestor->fromUpload($this->notebook(), $this->file, $this->title ?: null);

        $this->afterAdd($source);
        $this->file = null;
        $this->title = '';
    }

    public function addText(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'text' => ['required', 'string', 'min:10'],
        ], [
            'title.required' => 'Nhập tiêu đề nguồn.',
            'text.required' => 'Dán nội dung văn bản.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $source = $ingestor->fromText($this->notebook(), $this->title, $this->text);

        $this->afterAdd($source);
        $this->title = '';
        $this->text = '';
    }

    public function addDocument(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'documentId' => ['required', 'integer', Rule::exists('documents', 'id')],
        ], [
            'documentId.required' => 'Chọn một tài liệu trong môn.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $document = Document::query()->findOrFail($this->documentId);
        abort_unless($document->subject_id === $this->notebook()->subject_id, 403);

        $source = $ingestor->fromDocument($this->notebook(), $document);

        $this->afterAdd($source);
        $this->documentId = null;
    }

    protected function canAddSource(): bool
    {
        $count = $this->notebook()->sources()->count();

        if ($count >= NotebookConfig::maxSources()) {
            $this->error = 'Đã đạt giới hạn '.NotebookConfig::maxSources().' nguồn cho notebook này.';

            return false;
        }

        return true;
    }

    protected function afterAdd(NotebookSource $source): void
    {
        if ($source->status === 'failed') {
            $this->error = $source->error ?: 'Không trích được nội dung nguồn.';

            return;
        }

        $this->dispatch('notebook-source-added');
        session()->flash('notebook_status', 'Đã thêm nguồn: '.$source->title);
    }

    public function toggle(int $sourceId): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);
        $source->forceFill(['is_enabled' => ! $source->is_enabled])->save();
    }

    public function remove(int $sourceId, SourceIngestor $ingestor): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);

        if ($this->viewingSourceId === $source->id) {
            $this->viewingSourceId = null;
        }

        $ingestor->remove($source);
    }

    public function view(int $sourceId): void
    {
        $this->guard();
        $this->viewingSourceId = $sourceId;
        $this->dispatch('notebook-open-viewer');
    }

    public function closeViewer(): void
    {
        $this->viewingSourceId = null;
    }

    public function render(): View
    {
        $notebook = $this->notebook();

        return view('livewire.notebook.sources', [
            'sources' => $notebook->sources()->withCount('chunks')->get(),
            'documents' => Document::query()->orderByDesc('created_at')->limit(50)->get(),
            'viewing' => $this->viewingSourceId
                ? NotebookSource::query()->with('chunks')->find($this->viewingSourceId)
                : null,
            'maxSources' => NotebookConfig::maxSources(),
        ]);
    }
}
