<?php

namespace App\Livewire\Teacher;

use App\Enums\SubjectFeature;
use App\Models\Document;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Tài liệu')]
class DocumentsIndex extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public bool $isPublic = true;

    public $file = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Document::class);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Document::class);

        $this->resetForm();
        $this->showForm = true;
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
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,jpeg,png,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề tài liệu.',
            'file.required' => 'Vui lòng chọn tệp.',
            'file.max' => 'Tệp tối đa 20MB.',
            'file.mimes' => 'Định dạng tệp không được hỗ trợ.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Gate::authorize('create', Document::class);

        $subjectId = app(SubjectContext::class)->id();
        $path = $this->file->store("documents/{$subjectId}", 'public');

        Document::create([
            'subject_id' => $subjectId,
            'created_by' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description ?: null,
            'category' => $this->category ?: null,
            'file_path' => $path,
            'original_name' => $this->file->getClientOriginalName(),
            'mime' => $this->file->getMimeType(),
            'size' => $this->file->getSize(),
            'is_public' => $this->isPublic,
        ]);

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã tải tài liệu lên.');
    }

    public function togglePublic(int $id): void
    {
        $document = Document::query()->findOrFail($id);
        Gate::authorize('update', $document);

        $document->forceFill(['is_public' => ! $document->is_public])->save();

        session()->flash('status', 'Đã cập nhật hiển thị tài liệu.');
    }

    public function delete(int $id): void
    {
        $document = Document::query()->findOrFail($id);
        Gate::authorize('delete', $document);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        session()->flash('status', 'Đã xóa tài liệu.');
    }

    protected function resetForm(): void
    {
        $this->title = '';
        $this->description = '';
        $this->category = '';
        $this->isPublic = true;
        $this->file = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        return view('livewire.teacher.documents-index', [
            'documents' => Document::query()->with('creator')->orderByDesc('created_at')->get(),
            'subject' => $subject,
            'featureEnabled' => $subject?->hasFeature(SubjectFeature::Documents) ?? false,
        ]);
    }
}
