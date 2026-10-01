<div class="flex h-full min-h-0 flex-col pb-14 lg:pb-0"
    x-data="{ ...awawaNotebookPanels(), mobileTab: 'chat' }"
    :class="dragging ? 'select-none' : null"
    x-on:pointermove.window="onPointerMove($event)"
    x-on:pointerup.window="onPointerUp()"
    x-on:pointercancel.window="onPointerUp()"
    x-on:keydown.escape.window="dragging = null">
    <div class="flex h-12 shrink-0 items-center gap-2 border-b border-rule px-3 dark:border-night-700">
        <a href="{{ route('studio.ai') }}" wire:navigate
            class="flex min-h-9 min-w-9 shrink-0 items-center justify-center rounded-[10px] text-ink-faint transition-colors hover:bg-paper-2 hover:text-ink focus-visible:outline-2 focus-visible:outline-brand-600 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
            aria-label="Về danh sách sổ tay">
            <x-icon name="arrow-left" class="h-4 w-4" />
        </a>
        <p class="min-w-0 flex-1 truncate text-sm font-semibold text-ink dark:text-white">{{ $notebook->title }}</p>
        <p class="hidden shrink-0 truncate text-[11px] text-ink-faint sm:block dark:text-slate-500">{{ $notebook->subject?->name }}</p>
    </div>

    {{-- Tab cho mobile: chuyển tức thì phía client để không phải chờ server
        (hàng đợi Livewire có thể nghẽn sau request AI dài), wire:click giữ lại
        để đồng bộ state server. --}}
    <div class="tabs shrink-0 border-b border-rule px-3 lg:hidden dark:border-night-700">
        @foreach (['sources' => 'Nguồn', 'chat' => 'Chat', 'studio' => 'Soạn bài'] as $key => $label)
            <button type="button" @click="mobileTab = '{{ $key }}'" wire:click="$set('mobileTab', '{{ $key }}')"
                class="tab" :class="mobileTab === '{{ $key }}' ? 'tab-active' : ''">{{ $label }}</button>
        @endforeach
    </div>

    <div class="notebook-panes relative flex min-h-0 flex-1" x-ref="panes"
        :style="isDesktop ? `--nb-sources: ${sourcesWidth}px; --nb-studio: ${studioWidth}px` : null">
        <aside x-cloak :class="mobileTab === 'sources' ? 'flex' : 'hidden'" class="min-h-0 w-full flex-col border-rule lg:flex lg:w-[var(--nb-sources)] lg:shrink-0 lg:border-r dark:border-night-700">
            <livewire:notebook.sources :notebook-id="$notebook->id" :key="'sources-'.$notebook->id" />
        </aside>

        <div class="resize-handle hidden lg:flex" role="separator" aria-orientation="vertical"
            aria-label="Đổi bề rộng cột nguồn. Nhấn đôi để về mặc định."
            :aria-valuenow="sourcesWidth" :aria-valuemin="limits.sources[0]" :aria-valuemax="limits.sources[1]" tabindex="0"
            :class="dragging === 'sources' ? 'is-dragging' : null"
            @pointerdown.prevent="startDrag('sources', $event)"
            @dblclick="reset()"
            @keydown.left.prevent="nudge('sources', -16)"
            @keydown.right.prevent="nudge('sources', 16)"></div>

        <section x-cloak :class="mobileTab === 'chat' ? 'flex' : 'hidden'" class="min-h-0 min-w-0 flex-1 flex-col lg:flex">
            <livewire:notebook.chat :notebook-id="$notebook->id" :key="'chat-'.$notebook->id" />
        </section>

        <div class="resize-handle hidden lg:flex" role="separator" aria-orientation="vertical"
            aria-label="Đổi bề rộng cột studio. Nhấn đôi để về mặc định."
            :aria-valuenow="studioWidth" :aria-valuemin="limits.studio[0]" :aria-valuemax="limits.studio[1]" tabindex="0"
            :class="dragging === 'studio' ? 'is-dragging' : null"
            @pointerdown.prevent="startDrag('studio', $event)"
            @dblclick="reset()"
            @keydown.left.prevent="nudge('studio', -16)"
            @keydown.right.prevent="nudge('studio', 16)"></div>

        <aside x-cloak :class="mobileTab === 'studio' ? 'flex' : 'hidden'" class="min-h-0 w-full flex-col border-rule lg:flex lg:w-[var(--nb-studio)] lg:shrink-0 lg:border-l dark:border-night-700">
            <livewire:notebook.studio :notebook-id="$notebook->id" :key="'studio-'.$notebook->id" />
        </aside>
    </div>
</div>
