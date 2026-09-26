<div class="relative w-full" x-data="{ open: false, creating: false, editing: null, title: '' }"
    @click.outside="open = false; creating = false; editing = null"
    @keydown.escape.window="open = false; creating = false; editing = null">
    <div class="flex items-center gap-2">
        <span class="hidden text-xs font-semibold text-ink-faint sm:block dark:text-slate-500">Notebook</span>

        <button type="button" @click="open = ! open; creating = false"
            class="flex min-w-0 max-w-[16rem] flex-1 items-center gap-1.5 rounded-[10px] border border-rule px-2.5 py-1.5 text-left text-sm font-semibold text-ink transition-colors hover:bg-paper-2 sm:flex-none dark:border-night-700 dark:text-white dark:hover:bg-white/5"
            aria-haspopup="true" :aria-expanded="open ? 'true' : 'false'">
            <x-icon name="book" class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
            <span class="truncate">{{ $current?->title ?? 'Notebook' }}</span>
            <x-icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-ink-faint" />
        </button>

        <span class="tnum shrink-0 text-[11px] text-ink-faint dark:text-slate-500">
            {{ $notebooks->count() }}/{{ $maxNotebooks }}
        </span>

        @if ($atLimit)
            <span class="shrink-0 text-[11px] text-warning dark:text-amber-300">Đã đạt giới hạn</span>
        @else
            <button type="button" @click="creating = ! creating; open = false"
                class="flex shrink-0 items-center gap-1 rounded-[10px] border border-rule px-2.5 py-1.5 text-xs font-medium text-ink-soft transition-colors hover:bg-paper-2 dark:border-night-700 dark:text-slate-200 dark:hover:bg-white/5">
                <x-icon name="plus" class="h-3.5 w-3.5" />
                Tạo notebook
            </button>
        @endif
    </div>

    @if ($error)
        <div class="alert alert-error mt-2">{{ $error }}</div>
    @endif

    <div x-show="open" x-cloak
        class="panel absolute left-0 top-full z-40 mt-1 w-80 max-w-[92vw] p-2 shadow-lg">
        <p class="px-2 py-1.5 text-xs font-semibold text-ink dark:text-white">Notebook của tôi</p>

        <div class="max-h-72 space-y-0.5 overflow-y-auto">
            @foreach ($notebooks as $item)
                <div class="group flex items-center gap-1 rounded-[10px] px-2 py-1.5 hover:bg-paper-2 dark:hover:bg-white/5"
                    wire:key="nb-row-{{ $item->id }}">
                    <input type="text" x-show="editing === {{ $item->id }}" x-model="title" maxlength="120"
                        class="input py-1 text-xs"
                        @keydown.enter="$wire.rename({{ $item->id }}, title); editing = null; title = ''"
                        @keydown.escape="editing = null; title = ''"
                        aria-label="Tên notebook">

                    <a href="{{ route('studio.ai.notebook', ['notebookId' => $item->id]) }}" wire:navigate
                        x-show="editing !== {{ $item->id }}"
                        class="min-w-0 flex-1 truncate text-sm {{ $item->id === $notebookId ? 'font-semibold text-brand-700 dark:text-brand-300' : 'text-ink dark:text-slate-200' }}">
                        {{ $item->title }}
                    </a>

                    <span x-show="editing !== {{ $item->id }}"
                        class="shrink-0 text-[10px] text-ink-faint dark:text-slate-500">{{ $item->updated_at->diffForHumans() }}</span>

                    <button type="button" x-show="editing === {{ $item->id }}"
                        @click="$wire.rename({{ $item->id }}, title); editing = null; title = ''"
                        class="shrink-0 rounded-[8px] p-1.5 text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-white/10"
                        title="Lưu tên" aria-label="Lưu tên">
                        <x-icon name="check" class="h-4 w-4" />
                    </button>

                    <button type="button" x-show="editing !== {{ $item->id }}"
                        @click="editing = {{ $item->id }}; title = @js($item->title); $nextTick(() => $el.closest('div').querySelector('input[type=text]')?.focus())"
                        class="shrink-0 rounded-[8px] p-1 text-ink-faint opacity-0 transition-opacity hover:bg-paper-2 group-hover:opacity-100 focus:opacity-100 dark:hover:bg-white/10"
                        title="Đổi tên" aria-label="Đổi tên {{ $item->title }}">
                        <x-icon name="pencil" class="h-3.5 w-3.5" />
                    </button>

                    <button type="button" x-show="editing !== {{ $item->id }}"
                        @if ($item->canBeDeletedBy(auth()->user()))
                            wire:click="delete({{ $item->id }})"
                            wire:confirm="Xoá notebook “{{ $item->title }}” cùng toàn bộ nguồn, câu hỏi và nội dung đã tạo?"
                        @endif
                        @class(['shrink-0 rounded-[8px] p-1 text-ink-faint opacity-0 transition-opacity hover:bg-signal-soft hover:text-signal group-hover:opacity-100 focus:opacity-100 dark:hover:bg-red-500/10' => $item->canBeDeletedBy(auth()->user()), 'hidden' => ! $item->canBeDeletedBy(auth()->user())])
                        title="Xoá" aria-label="Xoá {{ $item->title }}">
                        <x-icon name="trash" class="h-3.5 w-3.5" />
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <form x-show="creating" x-cloak wire:submit="create"
        class="panel absolute left-0 top-full z-40 mt-1 w-80 max-w-[92vw] space-y-2 p-3 shadow-lg">
        <div>
            <label class="label" for="nb-new-title">Tên notebook mới</label>
            <input id="nb-new-title" type="text" class="input" maxlength="120" wire:model="newTitle"
                placeholder="VD: Chuyên đề bất đẳng thức">
            @error('newTitle') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" @click="creating = false" class="btn btn-ghost">Huỷ</button>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create">
                <span wire:loading.remove wire:target="create">Tạo</span>
                <span wire:loading wire:target="create">Đang tạo…</span>
            </button>
        </div>
    </form>
</div>
