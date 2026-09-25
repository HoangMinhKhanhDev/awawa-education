<?php

namespace App\Livewire\Admin\Subjects;

use App\Enums\SubjectFeature;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Quản lý môn học')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $color = '#2563EB';

    public string $description = '';

    public bool $isActive = true;

    public int $order = 0;

    /**
     * @var array<string, bool>
     */
    public array $features = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Subject::class);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Subject::class);

        $this->resetForm();
        $this->features = $this->defaultFeatures();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $subject = Subject::query()->findOrFail($id);
        Gate::authorize('update', $subject);

        $this->editingId = $subject->id;
        $this->name = $subject->name;
        $this->code = $subject->code;
        $this->color = $subject->color;
        $this->description = (string) $subject->description;
        $this->isActive = $subject->is_active;
        $this->order = $subject->order;
        $this->features = $this->defaultFeatures();

        foreach ($subject->features as $feature) {
            $key = $feature->feature instanceof SubjectFeature ? $feature->feature->value : (string) $feature->feature;
            $this->features[$key] = $feature->is_enabled;
        }

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
        $codeRule = Rule::unique(Subject::class, 'code');

        if ($this->editingId !== null) {
            $codeRule = $codeRule->ignore($this->editingId);
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', $codeRule],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['integer', 'min:0', 'max:999'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên môn.',
            'code.required' => 'Vui lòng nhập mã môn.',
            'code.regex' => 'Mã môn chỉ gồm chữ thường, số và dấu gạch ngang.',
            'code.unique' => 'Mã môn này đã tồn tại.',
            'color.regex' => 'Màu phải ở dạng #RRGGBB.',
        ];
    }

    public function updatedName(): void
    {
        if ($this->editingId === null && blank($this->code)) {
            $this->code = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId !== null) {
            $subject = Subject::query()->findOrFail($this->editingId);
            Gate::authorize('update', $subject);

            $subject->update([
                'name' => $this->name,
                'code' => $this->code,
                'color' => $this->color,
                'description' => $this->description ?: null,
                'order' => $this->order,
                'is_active' => $this->isActive,
            ]);
        } else {
            Gate::authorize('create', Subject::class);

            $subject = Subject::create([
                'name' => $this->name,
                'code' => $this->code,
                'color' => $this->color,
                'description' => $this->description ?: null,
                'order' => $this->order,
                'is_active' => $this->isActive,
            ]);
        }

        foreach (SubjectFeature::cases() as $feature) {
            $subject->features()->updateOrCreate(
                ['feature' => $feature->value],
                ['is_enabled' => (bool) ($this->features[$feature->value] ?? false)],
            );
        }

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã lưu môn học và cấu hình tính năng.');
    }

    public function delete(int $id): void
    {
        $subject = Subject::query()->findOrFail($id);
        Gate::authorize('delete', $subject);

        if ($subject->teachers()->exists() || $subject->students()->exists()) {
            session()->flash('error', 'Không thể xóa môn đang có giáo viên hoặc học sinh. Hãy chuyển họ sang môn khác trước.');

            return;
        }

        $subject->delete();

        session()->flash('status', 'Đã xóa môn học.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->color = '#2563EB';
        $this->description = '';
        $this->isActive = true;
        $this->order = 0;
        $this->features = $this->defaultFeatures();
        $this->resetErrorBag();
    }

    /**
     * @return array<string, bool>
     */
    protected function defaultFeatures(): array
    {
        $features = [];

        foreach (SubjectFeature::cases() as $feature) {
            $features[$feature->value] = $feature->defaultEnabled();
        }

        return $features;
    }

    public function render(): View
    {
        return view('livewire.admin.subjects.index', [
            'subjects' => Subject::query()
                ->withCount(['teachers', 'students', 'memberships'])
                ->orderBy('order')
                ->get(),
            'featureCases' => SubjectFeature::cases(),
        ]);
    }
}
