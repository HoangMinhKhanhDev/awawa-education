<?php

namespace App\Livewire\Notebook;

use App\Enums\ArtifactType;
use App\Enums\SubjectFeature;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Services\Ai\AiException;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\ArtifactPublisher;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class Studio extends Component
{
    #[Locked]
    public int $notebookId;

    public ?string $formType = null;

    public string $instruction = '';

    public int $count = 5;

    public string $questionType = 'mixed';

    public string $difficulty = 'medium';

    public float $points = 1;

    public bool $generating = false;

    public ?string $error = null;

    public ?int $previewId = null;

    public bool $publishPublic = true;

    public string $filterType = '';

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

    public function openForm(string $type): void
    {
        $this->guard();

        if (! in_array($type, $this->availableTypeValues(), true)) {
            $this->error = 'Loại nội dung này chưa được bật cho môn của bạn.';

            return;
        }

        $this->formType = $type;
        $this->instruction = '';
        $this->error = null;
        $this->resetErrorBag();
    }

    public function closeForm(): void
    {
        $this->formType = null;
        $this->instruction = '';
    }

    public function generate(ArtifactGenerator $generator): void
    {
        $this->guard();
        $this->error = null;

        $this->validate([
            'instruction' => ['required', 'string', 'min:4', 'max:1500'],
            'count' => ['integer', 'min:1', 'max:20'],
            'points' => ['numeric', 'min:0.25', 'max:100'],
        ], [
            'instruction.required' => 'Nhập yêu cầu cho nội dung cần tạo.',
        ]);

        $type = ArtifactType::tryFrom((string) $this->formType);

        if ($type === null) {
            return;
        }

        $this->generating = true;

        try {
            $data = $generator->generate(
                $this->notebook(),
                $type,
                [
                    'instruction' => $this->instruction,
                    'count' => $this->count,
                    'question_type' => $this->questionType,
                    'difficulty' => $this->difficulty,
                    'points' => $this->points,
                ],
                app(SubjectContext::class)->id() ?? $this->notebook()->subject_id,
                auth()->id(),
            );
        } catch (AiException|RuntimeException $exception) {
            $this->error = $exception->getMessage();
            $this->generating = false;

            return;
        }

        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebookId,
            'subject_id' => $this->notebook()->subject_id,
            'user_id' => auth()->id(),
            'type' => $type->value,
            'title' => $data['title'],
            'payload' => $data['payload'],
            'text_content' => $data['text'],
            'status' => 'draft',
        ]);

        $this->generating = false;
        $this->closeForm();
        $this->filterType = $type->value;
        $this->previewId = $artifact->id;

        $this->dispatch('notebook-artifact-created');
    }

    public function openPreview(int $id): void
    {
        $this->guard();
        $this->previewId = $id;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function publish(int $id, ArtifactPublisher $publisher): void
    {
        $this->guard();

        $artifact = $this->notebook()->artifacts()->findOrFail($id);

        try {
            $publisher->publish($artifact, $this->publishPublic);
        } catch (\Throwable $exception) {
            $this->error = 'Không xuất bản được: '.$exception->getMessage();

            return;
        }

        session()->flash('notebook_status', 'Đã xuất bản: '.$artifact->title);
        $this->dispatch('notebook-artifact-published');
    }

    public function delete(int $id): void
    {
        $this->guard();

        $this->notebook()->artifacts()->whereKey($id)->delete();

        if ($this->previewId === $id) {
            $this->previewId = null;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function availableTypeValues(): array
    {
        return $this->availableTypes()->map(fn (ArtifactType $type) => $type->value)->all();
    }

    /**
     * @return Collection<int, ArtifactType>
     */
    public function availableTypes(): Collection
    {
        $subject = $this->notebook()->subject;

        return collect(ArtifactType::cases())->filter(function (ArtifactType $type) use ($subject): bool {
            if ($subject === null) {
                return false;
            }

            return match ($type) {
                ArtifactType::Questions => $subject->hasFeature(SubjectFeature::QuestionBank),
                ArtifactType::Exam => $subject->hasFeature(SubjectFeature::Exams),
                ArtifactType::Document => $subject->hasFeature(SubjectFeature::Documents),
                ArtifactType::MindMap => $subject->hasFeature(SubjectFeature::KnowledgeMap),
                default => true,
            };
        })->values();
    }

    public function render(): View
    {
        $artifacts = $this->notebook()->artifacts()
            ->when($this->filterType !== '', fn ($query) => $query->where('type', $this->filterType))
            ->limit(30)
            ->get();

        return view('livewire.notebook.studio', [
            'types' => $this->availableTypes(),
            'artifacts' => $artifacts,
            'preview' => $this->previewId ? $this->notebook()->artifacts()->find($this->previewId) : null,
            'hasSources' => $this->notebook()->enabledSourceIds() !== [],
        ]);
    }
}
