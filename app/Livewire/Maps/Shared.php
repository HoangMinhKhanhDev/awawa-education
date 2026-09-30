<?php

namespace App\Livewire\Maps;

use App\Enums\MapVisibility;
use App\Models\KnowledgeMap;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Shared extends Component
{
    #[Locked]
    public int $mapId;

    #[Locked]
    public string $token;

    public function mount(string $token): void
    {
        $map = KnowledgeMap::query()->where('share_token', $token)->first();

        if ($map === null || $map->visibility !== MapVisibility::Link) {
            abort(404);
        }

        $this->mapId = $map->id;
        $this->token = $token;
    }

    public function render(): View
    {
        $map = KnowledgeMap::query()
            ->whereKey($this->mapId)
            ->where('share_token', $this->token)
            ->where('visibility', MapVisibility::Link->value)
            ->firstOrFail();
        $initial = $map->latestVersion?->scene ?? [
            'type' => 'excalidraw',
            'elements' => [],
            'appState' => ['viewBackgroundColor' => '#ffffff'],
            'files' => [],
        ];

        $initial['appState'] = array_merge($initial['appState'] ?? [], ['viewModeEnabled' => true]);

        return view('livewire.maps.shared', [
            'map' => $map,
            'initialJson' => json_encode($initial),
        ])->title($map->title.' · Sơ đồ chia sẻ');
    }
}
