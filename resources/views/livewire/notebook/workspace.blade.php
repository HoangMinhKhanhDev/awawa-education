<div class="space-y-5">
    <div class="page-head">
        <div>
            <h1 class="page-title">Notebook</h1>
            <p class="page-sub">
                {{ $notebook->title }}
                <span class="mx-1.5 text-rule-strong dark:text-night-700">/</span>
                <span style="color: {{ $notebook->subject?->color }}">{{ $notebook->subject?->name }}</span>
            </p>
        </div>
    </div>

    {{-- Tab cho mobile --}}
    <div class="tabs lg:hidden">
        @foreach (['sources' => 'Nguồn', 'chat' => 'Chat', 'studio' => 'Studio'] as $key => $label)
            <button type="button" wire:click="$set('mobileTab', '{{ $key }}')"
                class="tab {{ $mobileTab === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,290px)_minmax(0,1fr)_minmax(0,330px)]">
        <section class="min-w-0 {{ $mobileTab === 'sources' ? '' : 'hidden' }} lg:block">
            <livewire:notebook.sources :notebook-id="$notebook->id" :key="'sources-'.$notebook->id" />
        </section>

        <section class="min-w-0 {{ $mobileTab === 'chat' ? '' : 'hidden' }} lg:block">
            <livewire:notebook.chat :notebook-id="$notebook->id" :key="'chat-'.$notebook->id" />
        </section>

        <section class="min-w-0 {{ $mobileTab === 'studio' ? '' : 'hidden' }} lg:block">
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Studio</h2>
                </div>
                <div class="space-y-2 p-5">
                    <p class="text-sm text-ink-soft dark:text-slate-400">
                        Tạo nội dung từ nguồn: câu hỏi, đề thi, tài liệu, flashcards, sơ đồ tư duy, đề cương, bản tin.
                    </p>
                    <p class="text-xs text-ink-faint dark:text-slate-500">Sẽ bổ sung ở bước tiếp theo.</p>
                </div>
            </div>
        </section>
    </div>
</div>
