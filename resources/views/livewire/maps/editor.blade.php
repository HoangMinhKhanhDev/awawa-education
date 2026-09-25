@php
    $baseName = \Illuminate\Support\Str::slug($map->title) ?: 'so-do';
    $initial = json_decode($initialSceneJson, true) ?: [];
    if ($readOnly) {
        $initial['appState'] = array_merge($initial['appState'] ?? [], ['viewModeEnabled' => true]);
    }
    $initialJson = json_encode($initial);
@endphp

@vite('resources/js/whiteboard.jsx')

<div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
            <a href="{{ route('map') }}" wire:navigate class="text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">← Sơ đồ kiến thức</a>
            @if ($readOnly)
                <h1 class="mt-1 truncate text-xl font-bold text-slate-900 dark:text-white">{{ $map->title }}</h1>
                <p class="text-xs text-slate-400">Chế độ chỉ xem · tác giả {{ $map->owner?->name }}</p>
            @else
                <input type="text" class="mt-1 w-full max-w-md rounded-lg border border-transparent bg-transparent px-2 py-1 text-xl font-bold text-slate-900 hover:border-slate-200 focus:border-brand-500 focus:outline-none dark:text-white dark:hover:border-white/10"
                    wire:model="title" aria-label="Tên sơ đồ">
                @error('title') <p class="px-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="hidden text-xs text-slate-400 sm:inline">v{{ $map->current_version }}</span>

            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button" @click="open = ! open" class="btn btn-outline px-3 py-1.5 text-xs">
                    Xuất
                    <span class="text-[10px]">▼</span>
                </button>
                <div x-show="open" x-transition x-cloak
                    class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-white/10 dark:bg-night-800">
                    <button type="button" @click="window.AwawaWhiteboard.exportPng('{{ $baseName }}.png'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">Ảnh PNG</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportSvg('{{ $baseName }}.svg'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">Ảnh SVG</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportPdf('{{ $baseName }}.pdf'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">PDF</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportJson('{{ $baseName }}.excalidraw'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">JSON</button>
                </div>
            </div>

            @unless ($readOnly)
                <button type="button" onclick="window.__awawaSaveMap && window.__awawaSaveMap()" class="btn btn-primary px-4 py-1.5 text-xs" id="map-save-button">
                    Lưu
                </button>
            @endunless
        </div>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @error('scene')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
        <div class="absolute left-1/2 top-12 z-20 -translate-x-1/2" wire:loading wire:target="save,restore">
            <span class="rounded-full bg-slate-900/80 px-3 py-1 text-xs text-white">Đang lưu…</span>
        </div>
        <div id="excalidraw-app" wire:ignore style="height: calc(100vh - 240px); min-height: 460px;"></div>
    </div>

    <div class="card">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lịch sử phiên bản</h2>
        <div class="mt-3 space-y-2">
            @foreach ($versions as $version)
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-white/10" wire:key="ver-{{ $version->id }}">
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">v{{ $version->version }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-slate-700 dark:text-slate-200">{{ $version->label ?? 'Bản lưu' }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $version->creator?->name }}
                            @if ($version->created_at) · {{ $version->created_at->format('d/m/Y H:i') }} @endif
                        </p>
                    </div>
                    @unless ($readOnly)
                        @if ($version->version !== $map->current_version)
                            <button type="button" wire:click="restore({{ $version->id }})" wire:confirm="Khôi phục về phiên bản v{{ $version->version }}?"
                                class="btn btn-ghost px-3 py-1.5 text-xs">Khôi phục</button>
                        @else
                            <span class="text-xs text-slate-400">Hiện tại</span>
                        @endif
                    @endunless
                </div>
            @endforeach
        </div>
    </div>

    @script
    <script>
        const mountWhiteboard = () => {
            const el = document.getElementById('excalidraw-app');

            if (! el || ! window.AwawaWhiteboard || el.dataset.mounted === '1') {
                return;
            }

            el.dataset.mounted = '1';

            window.AwawaWhiteboard.mount(el, {
                initialData: @js($initial),
                onChange: () => {},
            });
        };

        const bootWhiteboard = async () => {
            if (! window.AwawaWhiteboard) {
                await new Promise((resolve) => setTimeout(resolve, 120));
            }

            mountWhiteboard();
        };

        bootWhiteboard();

        window.__awawaSaveMap = () => {
            if (! window.AwawaWhiteboard) return;

            const json = window.AwawaWhiteboard.getSceneJson();
            $wire.set('scene', json).then(() => $wire.save());
        };

        @if (! $readOnly)
            if (! window.__awawaAutosave) {
                window.__awawaAutosave = setInterval(() => {
                    if (window.AwawaWhiteboard && window.AwawaWhiteboard.isDirty() && window.__awawaSaveMap) {
                        window.__awawaSaveMap();
                    }
                }, 45000);
            }

            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    window.__awawaSaveMap && window.__awawaSaveMap();
                }
            });
        @endif
    </script>
    @endscript
</div>
