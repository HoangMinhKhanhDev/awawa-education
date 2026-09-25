<?php

namespace App\Livewire\Maps;

use App\Enums\MapVisibility;
use App\Enums\SubjectFeature;
use App\Models\KnowledgeMap;
use App\Models\KnowledgeMapVersion;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sơ đồ kiến thức')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $visibility = 'private';

    public ?string $sharedUrl = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', KnowledgeMap::class);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', KnowledgeMap::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $map = KnowledgeMap::query()->findOrFail($id);
        Gate::authorize('update', $map);

        $this->editingId = $map->id;
        $this->title = $map->title;
        $this->description = (string) $map->description;
        $this->visibility = $map->visibility->value;
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
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:private,subject,link'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên sơ đồ.',
            'title.min' => 'Tên sơ đồ quá ngắn.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId !== null) {
            $map = KnowledgeMap::query()->findOrFail($this->editingId);
            Gate::authorize('update', $map);

            $map->update([
                'title' => $this->title,
                'description' => $this->description ?: null,
                'visibility' => $this->visibility,
            ]);
        } else {
            Gate::authorize('create', KnowledgeMap::class);

            $map = KnowledgeMap::create([
                'subject_id' => app(SubjectContext::class)->id() ?? auth()->user()?->subject_id,
                'owner_id' => auth()->id(),
                'title' => $this->title,
                'description' => $this->description ?: null,
                'visibility' => $this->visibility,
                'current_version' => 1,
            ]);

            KnowledgeMapVersion::create([
                'knowledge_map_id' => $map->id,
                'version' => 1,
                'scene' => [
                    'type' => 'excalidraw',
                    'elements' => [],
                    'appState' => ['viewBackgroundColor' => '#ffffff'],
                    'files' => [],
                ],
                'label' => 'Bản khởi tạo',
                'created_by' => auth()->id(),
            ]);

            $this->showForm = false;
            $this->resetForm();

            $this->redirect(route('maps.edit', $map), navigate: true);

            return;
        }

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã lưu sơ đồ.');
    }

    public function duplicate(int $id): void
    {
        $map = KnowledgeMap::query()->with('latestVersion')->findOrFail($id);
        Gate::authorize('view', $map);

        $copy = KnowledgeMap::create([
            'subject_id' => $map->subject_id,
            'owner_id' => auth()->id(),
            'title' => Str::limit($map->title.' (bản sao)', 180, ''),
            'description' => $map->description,
            'visibility' => MapVisibility::Private,
            'current_version' => 1,
        ]);

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $copy->id,
            'version' => 1,
            'scene' => $map->latestVersion?->scene ?? [
                'type' => 'excalidraw',
                'elements' => [],
                'appState' => ['viewBackgroundColor' => '#ffffff'],
                'files' => [],
            ],
            'label' => 'Sao chép từ '.$map->title,
            'created_by' => auth()->id(),
        ]);

        session()->flash('status', 'Đã nhân bản sơ đồ.');
    }

    public function share(int $id): void
    {
        $map = KnowledgeMap::query()->findOrFail($id);
        Gate::authorize('share', $map);

        $map->forceFill(['visibility' => MapVisibility::Link])->save();
        $this->sharedUrl = $map->shareUrl();

        session()->flash('status', 'Đã bật chia sẻ bằng liên kết.');
    }

    public function delete(int $id): void
    {
        $map = KnowledgeMap::query()->findOrFail($id);
        Gate::authorize('delete', $map);

        $map->delete();

        session()->flash('status', 'Đã xóa sơ đồ.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->visibility = MapVisibility::Private->value;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $user = auth()->user();
        $subject = app(SubjectContext::class)->subject();

        $query = KnowledgeMap::query()
            ->with(['owner', 'latestVersion'])
            ->orderByDesc('updated_at');

        if ($user->isStudent()) {
            $query->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhere('visibility', MapVisibility::Subject->value);
            });
        }

        return view('livewire.maps.index', [
            'maps' => $query->get(),
            'subject' => $subject,
            'visibilities' => MapVisibility::cases(),
            'featureEnabled' => $subject?->hasFeature(SubjectFeature::KnowledgeMap) ?? false,
            'canCreate' => $user->isSuperAdmin() || $user->subject_id !== null,
        ]);
    }
}
