@php
    $baseName = \Illuminate\Support\Str::slug($map->title) ?: 'so-do';
    $initial = json_decode($initialJson, true) ?: [];
@endphp

@vite('resources/js/whiteboard.jsx')

<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">{{ $map->title }}</h1>
            <p class="page-sub">Sơ đồ chia sẻ, chỉ xem — tác giả {{ $map->owner?->name }}</p>
        </div>

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
    </div>

    <div class="overflow-hidden rounded-[14px] border border-rule bg-white dark:border-night-700 dark:bg-night-800">
        <div id="excalidraw-shared" wire:ignore style="height: calc(100vh - 200px); min-height: 460px;"></div>
    </div>

    @script
    <script>
        const bootShared = async () => {
            const el = document.getElementById('excalidraw-shared');

            if (! el) return;

            for (let i = 0; i < 40 && ! window.AwawaWhiteboard; i++) {
                await new Promise((resolve) => setTimeout(resolve, 120));
            }

            if (! window.AwawaWhiteboard || el.dataset.mounted === '1') return;

            el.dataset.mounted = '1';

            window.AwawaWhiteboard.mount(el, {
                initialData: @js($initial),
            });
        };

        bootShared();
    </script>
    @endscript
</div>
