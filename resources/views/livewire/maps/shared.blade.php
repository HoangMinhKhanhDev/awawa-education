@php
    $baseName = \Illuminate\Support\Str::slug($map->title) ?: 'so-do';
    $initial = json_decode($initialJson, true) ?: [];
@endphp

@vite('resources/js/whiteboard.jsx')

<div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium text-brand-600 dark:text-brand-400">Sơ đồ được chia sẻ · chỉ xem</p>
            <h1 class="mt-1 truncate text-xl font-bold text-slate-900 dark:text-white">{{ $map->title }}</h1>
            <p class="text-xs text-slate-400">Tác giả {{ $map->owner?->name }}</p>
        </div>

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <button type="button" @click="open = ! open" class="btn btn-outline px-3 py-1.5 text-xs">
                Xuất <span class="text-[10px]">▼</span>
            </button>
            <div x-show="open" x-transition x-cloak
                class="absolute right-0 z-30 mt-1 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-white/10 dark:bg-night-800">
                <button type="button" @click="window.AwawaWhiteboard.exportPng('{{ $baseName }}.png'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">Ảnh PNG</button>
                <button type="button" @click="window.AwawaWhiteboard.exportSvg('{{ $baseName }}.svg'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">Ảnh SVG</button>
                <button type="button" @click="window.AwawaWhiteboard.exportPdf('{{ $baseName }}.pdf'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">PDF</button>
                <button type="button" @click="window.AwawaWhiteboard.exportJson('{{ $baseName }}.excalidraw'); open = false" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-white/5">JSON</button>
            </div>
        </div>
    </header>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
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
