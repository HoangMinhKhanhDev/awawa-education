<?php

namespace App\Livewire\Maps;

use App\Models\KnowledgeMap;
use App\Models\KnowledgeMapVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Editor extends Component
{
    public int $mapId;

    public string $title = '';

    public string $scene = '';

    public bool $readOnly = false;

    public function mount(KnowledgeMap $map): void
    {
        Gate::authorize('view', $map);

        $this->mapId = $map->id;
        $this->title = $map->title;
        $this->readOnly = ! Gate::allows('update', $map);
    }

    public function save(): void
    {
        $map = KnowledgeMap::query()->findOrFail($this->mapId);
        Gate::authorize('update', $map);

        $this->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
        ], [
            'title.required' => 'Vui lòng nhập tên sơ đồ.',
        ]);

        $decoded = json_decode($this->scene, true);

        if (! is_array($decoded)) {
            $this->addError('scene', 'Không đọc được dữ liệu sơ đồ.');

            return;
        }

        $nextVersion = $map->current_version + 1;

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => $nextVersion,
            'scene' => $decoded,
            'label' => 'Lưu tự động',
            'created_by' => auth()->id(),
        ]);

        $map->update([
            'title' => $this->title,
            'current_version' => $nextVersion,
        ]);

        $this->dispatch('map-saved');

        session()->flash('status', 'Đã lưu sơ đồ (phiên bản '.$nextVersion.').');
    }

    public function restore(int $versionId): void
    {
        $map = KnowledgeMap::query()->findOrFail($this->mapId);
        Gate::authorize('update', $map);

        $version = KnowledgeMapVersion::query()
            ->where('knowledge_map_id', $map->id)
            ->findOrFail($versionId);

        $nextVersion = $map->current_version + 1;

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => $nextVersion,
            'scene' => $version->scene,
            'label' => 'Khôi phục từ v'.$version->version,
            'created_by' => auth()->id(),
        ]);

        $map->update(['current_version' => $nextVersion]);

        session()->flash('status', 'Đã khôi phục phiên bản v'.$version->version.'.');

        $this->redirect(route('maps.edit', $map), navigate: true);
    }

    public function render(): View
    {
        $map = KnowledgeMap::query()->findOrFail($this->mapId);
        $latest = $map->latestVersion;

        return view('livewire.maps.editor', [
            'map' => $map,
            'initialSceneJson' => json_encode($latest?->scene ?? [
                'type' => 'excalidraw',
                'elements' => [],
                'appState' => ['viewBackgroundColor' => '#ffffff'],
                'files' => [],
            ]),
            'versions' => $map->versions()->with('creator')->limit(20)->get(),
        ])->title($map->title.' · Sơ đồ');
    }
}
