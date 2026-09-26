@php
    $baseName = \Illuminate\Support\Str::slug($map->title) ?: 'so-do';
    $initial = json_decode($initialSceneJson, true) ?: [];
    if ($readOnly) {
        $initial['appState'] = array_merge($initial['appState'] ?? [], ['viewModeEnabled' => true]);
    }
    $initialJson = json_encode($initial);
@endphp

@vite('resources/js/whiteboard.jsx')

<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <a href="{{ route('map') }}" wire:navigate class="text-sm text-ink-soft hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Quay lại sơ đồ</a>
            @if ($readOnly)
                <h1 class="page-title mt-1">{{ $map->title }}</h1>
                <p class="page-sub">Chỉ xem — tác giả {{ $map->owner?->name }}</p>
            @else
                <input type="text"
                    class="mt-1 w-full max-w-xl rounded-[10px] border border-transparent bg-transparent px-2 py-1 font-serif text-[26px] font-semibold leading-tight tracking-[-0.01em] text-ink hover:border-rule focus:border-brand-500 focus:outline-none dark:text-white dark:hover:border-night-700"
                    wire:model="title" aria-label="Tên sơ đồ">
                @error('title') <p class="px-2 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="tnum hidden text-xs text-ink-faint sm:inline dark:text-slate-500">phiên bản {{ $map->current_version }}</span>

            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button" @click="open = ! open" class="btn btn-outline px-3.5 py-2 text-xs">Xuất</button>
                <div x-show="open" x-transition.opacity x-cloak
                    class="panel absolute right-0 z-30 mt-1 w-44 overflow-hidden py-1">
                    <button type="button" @click="window.AwawaWhiteboard.exportPng('{{ $baseName }}.png'); open = false" class="block w-full px-3.5 py-2 text-left text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">Ảnh PNG</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportSvg('{{ $baseName }}.svg'); open = false" class="block w-full px-3.5 py-2 text-left text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">Ảnh SVG</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportPdf('{{ $baseName }}.pdf'); open = false" class="block w-full px-3.5 py-2 text-left text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">PDF</button>
                    <button type="button" @click="window.AwawaWhiteboard.exportJson('{{ $baseName }}.excalidraw'); open = false" class="block w-full px-3.5 py-2 text-left text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">JSON</button>
                </div>
            </div>

            @unless ($readOnly)
                <button type="button" onclick="window.__awawaSaveMap && window.__awawaSaveMap()" class="btn btn-primary px-4 py-2 text-xs" id="map-save-button">Lưu</button>
            @endunless
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @error('scene')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    <div class="relative overflow-hidden rounded-[14px] border border-rule bg-white dark:border-night-700 dark:bg-night-800">
        <div class="absolute left-1/2 top-3 z-20 -translate-x-1/2" wire:loading wire:target="save,restore">
            <span class="rounded-full bg-night-900/80 px-3 py-1 text-xs text-white">Đang lưu…</span>
        </div>
        <div id="excalidraw-app" wire:ignore style="height: calc(100vh - 240px); min-height: 460px;"></div>
    </div>

    <div class="panel">
        <details class="group">
            <summary class="flex cursor-pointer items-center justify-between px-5 py-3">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lịch sử phiên bản</h2>
                <span class="text-xs text-ink-faint transition-transform group-open:rotate-180 dark:text-slate-500">▾</span>
            </summary>
            <div class="divide-y divide-rule border-t border-rule dark:divide-night-700 dark:border-night-700">
                @foreach ($versions as $version)
                    <div class="flex items-center gap-4 px-5 py-3" wire:key="ver-{{ $version->id }}">
                        <span class="tnum w-12 shrink-0 text-sm font-semibold text-ink-soft dark:text-slate-300">v{{ $version->version }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink dark:text-slate-200">{{ $version->label ?? 'Bản lưu' }}</p>
                            <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                {{ $version->creator?->name }}@if ($version->created_at)<span class="mx-1.5">—</span>{{ $version->created_at->format('d/m/Y H:i') }}@endif
                            </p>
                        </div>
                        @unless ($readOnly)
                            @if ($version->version !== $map->current_version)
                                <button type="button" wire:click="restore({{ $version->id }})" wire:confirm="Khôi phục về phiên bản v{{ $version->version }}?"
                                    class="btn btn-outline px-3 py-1.5 text-xs">Khôi phục</button>
                            @else
                                <span class="chip chip-brand">Hiện tại</span>
                            @endif
                        @endunless
                    </div>
                @endforeach
            </div>
        </details>
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
