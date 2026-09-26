<div class="flex h-full min-h-0 flex-col pb-14 lg:pb-0">
    {{-- Tab cho mobile --}}
    <div class="tabs shrink-0 border-b border-rule px-3 lg:hidden dark:border-night-700">
        @foreach (['sources' => 'Nguồn', 'chat' => 'Chat', 'studio' => 'Studio'] as $key => $label)
            <button type="button" wire:click="$set('mobileTab', '{{ $key }}')"
                class="tab {{ $mobileTab === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="flex min-h-0 flex-1">
        <aside class="{{ $mobileTab === 'sources' ? 'flex' : 'hidden' }} min-h-0 w-full flex-col border-rule lg:flex lg:w-[300px] lg:shrink-0 lg:border-r dark:border-night-700">
            <livewire:notebook.sources :notebook-id="$notebook->id" :key="'sources-'.$notebook->id" />
        </aside>

        <section class="{{ $mobileTab === 'chat' ? 'flex' : 'hidden' }} min-h-0 min-w-0 flex-1 flex-col lg:flex">
            <livewire:notebook.chat :notebook-id="$notebook->id" :key="'chat-'.$notebook->id" />
        </section>

        <aside class="{{ $mobileTab === 'studio' ? 'flex' : 'hidden' }} min-h-0 w-full flex-col border-rule lg:flex lg:w-[360px] lg:shrink-0 lg:border-l dark:border-night-700">
            <livewire:notebook.studio :notebook-id="$notebook->id" :key="'studio-'.$notebook->id" />
        </aside>
    </div>
</div>
