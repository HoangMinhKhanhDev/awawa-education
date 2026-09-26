<div class="flex h-full min-h-0 flex-col pb-14 lg:pb-0"
    x-data="awawaNotebookPanels()"
    :class="dragging ? 'select-none' : null"
    x-on:pointermove.window="onPointerMove($event)"
    x-on:pointerup.window="onPointerUp()"
    x-on:pointercancel.window="onPointerUp()"
    x-on:keydown.escape.window="dragging = null">
    <div class="flex h-12 shrink-0 items-center border-b border-rule px-3 dark:border-night-700">
        <livewire:notebook.manager :notebook-id="$notebook->id" :key="'manager-'.$notebook->id" />
    </div>

    {{-- Tab cho mobile --}}
    <div class="tabs shrink-0 border-b border-rule px-3 lg:hidden dark:border-night-700">
        @foreach (['sources' => 'Nguồn', 'chat' => 'Chat', 'studio' => 'Studio'] as $key => $label)
            <button type="button" wire:click="$set('mobileTab', '{{ $key }}')"
                class="tab {{ $mobileTab === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="notebook-panes relative flex min-h-0 flex-1" x-ref="panes"
        :style="isDesktop ? `--nb-sources: ${sourcesWidth}px; --nb-studio: ${studioWidth}px` : null">
        <aside class="{{ $mobileTab === 'sources' ? 'flex' : 'hidden' }} min-h-0 w-full flex-col border-rule lg:flex lg:w-[var(--nb-sources)] lg:shrink-0 lg:border-r dark:border-night-700">
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

        <section class="{{ $mobileTab === 'chat' ? 'flex' : 'hidden' }} min-h-0 min-w-0 flex-1 flex-col lg:flex">
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

        <aside class="{{ $mobileTab === 'studio' ? 'flex' : 'hidden' }} min-h-0 w-full flex-col border-rule lg:flex lg:w-[var(--nb-studio)] lg:shrink-0 lg:border-l dark:border-night-700">
            <livewire:notebook.studio :notebook-id="$notebook->id" :key="'studio-'.$notebook->id" />
        </aside>
    </div>
</div>
