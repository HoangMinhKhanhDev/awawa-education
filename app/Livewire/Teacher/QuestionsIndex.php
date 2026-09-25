<?php

namespace App\Livewire\Teacher;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Enums\SubjectFeature;
use App\Models\Question;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Ngân hàng câu hỏi')]
class QuestionsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $difficultyFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'multiple_choice';

    public string $content = '';

    public string $answer = '';

    public string $explanation = '';

    public string $difficulty = 'medium';

    public float $points = 1;

    public string $topic = '';

    public string $tagsInput = '';

    public bool $isActive = true;

    /**
     * @var array<int, array{content: string, is_correct: bool}>
     */
    public array $options = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Question::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDifficultyFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Question::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $question = Question::query()->with('options')->findOrFail($id);
        Gate::authorize('update', $question);

        $this->editingId = $question->id;
        $this->type = $question->type->value;
        $this->content = $question->content;
        $this->answer = (string) $question->answer;
        $this->explanation = (string) $question->explanation;
        $this->difficulty = $question->difficulty->value;
        $this->points = (float) $question->points;
        $this->topic = (string) $question->topic;
        $this->tagsInput = implode(', ', $question->tags ?? []);
        $this->isActive = $question->is_active;
        $this->options = $question->options
            ->map(fn ($option) => ['content' => $option->content, 'is_correct' => $option->is_correct])
            ->values()
            ->all();

        if ($this->options === []) {
            $this->options = $this->blankOptions();
        }

        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function addOption(): void
    {
        $this->options[] = ['content' => '', 'is_correct' => false];
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);

        if ($this->options === []) {
            $this->options = $this->blankOptions();
        }
    }

    public function markCorrect(int $index): void
    {
        foreach ($this->options as $i => $option) {
            $this->options[$i]['is_correct'] = $i === $index ? ! $option['is_correct'] : false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(QuestionType::values())],
            'content' => ['required', 'string', 'min:3', 'max:5000'],
            'difficulty' => ['required', Rule::in(Difficulty::values())],
            'points' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'topic' => ['nullable', 'string', 'max:120'],
            'answer' => ['nullable', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung câu hỏi.',
            'content.min' => 'Nội dung câu hỏi quá ngắn.',
            'points.min' => 'Điểm tối thiểu là 0.25.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $type = QuestionType::from($this->type);

        if ($type === QuestionType::MultipleChoice) {
            $normalized = $this->normalizedOptions();

            if (count($normalized) < 2) {
                $this->addError('options', 'Cần ít nhất 2 lựa chọn cho câu hỏi trắc nghiệm.');

                return;
            }

            if (! collect($normalized)->contains(fn ($option) => $option['is_correct'])) {
                $this->addError('options', 'Cần chọn ít nhất một đáp án đúng.');

                return;
            }
        }

        if ($type === QuestionType::FillBlank && blank($this->answer)) {
            $this->addError('answer', 'Vui lòng nhập đáp án cho câu hỏi điền khuyết.');

            return;
        }

        $attributes = [
            'type' => $type,
            'content' => $this->content,
            'answer' => $this->answer ?: null,
            'explanation' => $this->explanation ?: null,
            'difficulty' => $this->difficulty,
            'points' => $this->points,
            'topic' => $this->topic ?: null,
            'tags' => $this->parsedTags(),
            'is_active' => $this->isActive,
        ];

        if ($this->editingId !== null) {
            $question = Question::query()->findOrFail($this->editingId);
            Gate::authorize('update', $question);
            $question->update($attributes);
        } else {
            Gate::authorize('create', Question::class);

            $attributes['subject_id'] = app(SubjectContext::class)->id() ?? auth()->user()?->subject_id;
            $attributes['created_by'] = auth()->id();
            $question = Question::create($attributes);
        }

        $question->options()->delete();

        if ($type === QuestionType::MultipleChoice) {
            foreach ($this->normalizedOptions() as $index => $option) {
                $question->options()->create([
                    'content' => $option['content'],
                    'is_correct' => $option['is_correct'],
                    'order' => $index,
                ]);
            }
        }

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã lưu câu hỏi.');
    }

    public function toggleActive(int $id): void
    {
        $question = Question::query()->findOrFail($id);
        Gate::authorize('update', $question);

        $question->forceFill(['is_active' => ! $question->is_active])->save();

        session()->flash('status', 'Đã cập nhật trạng thái câu hỏi.');
    }

    public function delete(int $id): void
    {
        $question = Question::query()->findOrFail($id);
        Gate::authorize('delete', $question);

        $question->delete();

        session()->flash('status', 'Đã xóa câu hỏi.');
    }

    /**
     * @return array<int, array{content: string, is_correct: bool}>
     */
    protected function normalizedOptions(): array
    {
        return collect($this->options)
            ->map(fn (array $option) => [
                'content' => trim((string) ($option['content'] ?? '')),
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ])
            ->filter(fn (array $option) => $option['content'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function parsedTags(): array
    {
        return collect(explode(',', $this->tagsInput))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->type = QuestionType::MultipleChoice->value;
        $this->content = '';
        $this->answer = '';
        $this->explanation = '';
        $this->difficulty = Difficulty::Medium->value;
        $this->points = 1;
        $this->topic = '';
        $this->tagsInput = '';
        $this->isActive = true;
        $this->options = $this->blankOptions();
        $this->resetErrorBag();
    }

    /**
     * @return array<int, array{content: string, is_correct: bool}>
     */
    protected function blankOptions(): array
    {
        return [
            ['content' => '', 'is_correct' => false],
            ['content' => '', 'is_correct' => false],
        ];
    }

    public function render(): View
    {
        $subject = app(SubjectContext::class)->subject();

        $questions = Question::query()
            ->with('options')
            ->when($this->search !== '', fn ($query) => $query->where('content', 'like', '%'.$this->search.'%'))
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->difficultyFilter !== '', fn ($query) => $query->where('difficulty', $this->difficultyFilter))
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.teacher.questions-index', [
            'questions' => $questions,
            'subject' => $subject,
            'types' => QuestionType::cases(),
            'difficulties' => Difficulty::cases(),
            'featureEnabled' => $subject?->hasFeature(SubjectFeature::QuestionBank) ?? false,
        ]);
    }
}
