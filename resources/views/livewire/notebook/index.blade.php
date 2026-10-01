<div class="mx-auto w-full max-w-3xl px-4 pb-28 pt-6 lg:px-8 lg:pb-14 lg:pt-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-serif text-2xl font-semibold text-ink dark:text-white">Sổ tay AI</h1>
            <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Mỗi sổ tay giữ nguồn, trò chuyện và bản soạn riêng.</p>
        </div>
        @unless ($atLimit)
            <button type="button" wire:click="$set('creating', true)"
                class="btn btn-primary" wire:loading.attr="disabled" wire:target="create">
                <x-icon name="plus" class="h-4 w-4" />
                Notebook mới
            </button>
        @endunless
    </div>

    @if (session('notebook_status'))
        <div class="alert alert-success mt-4">{{ session('notebook_status') }}</div>
    @endif

    @if ($error)
        <div class="alert alert-error mt-4">{{ $error }}</div>
    @endif

    @if ($creating && ! $atLimit)
        <form wire:submit="create" class="panel panel-pad mt-4 flex gap-2">
            <input type="text" wire:model="newTitle" maxlength="120" autofocus placeholder="VD: Chuyên đề bất đẳng thức"
                class="input min-w-0 flex-1" aria-label="Tên notebook mới">
            <button type="button" wire:click="$set('creating', false)" class="btn btn-ghost shrink-0">Huỷ</button>
            <button type="submit" class="btn btn-primary shrink-0" wire:loading.attr="disabled" wire:target="create">
                <span wire:loading.remove wire:target="create">Tạo</span>
                <span wire:loading wire:target="create">Đang tạo…</span>
            </button>
        </form>
        @error('newTitle') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
    @endif

    @if ($atLimit)
        <p class="mt-4 text-xs text-warning dark:text-amber-300">Đã đạt giới hạn {{ $maxNotebooks }} notebook.</p>
    @endif

    @if ($resume)
        <a href="{{ route('studio.ai.notebook', ['notebookId' => $resume->id]) }}" wire:navigate
            class="panel panel-pad mt-4 flex items-center gap-3 transition-colors hover:border-brand-300 dark:hover:border-brand-500/40">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-brand-600 text-white">
                <x-icon name="arrow-right" class="h-5 w-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs text-ink-faint dark:text-slate-500">Tiếp tục làm việc</span>
                <span class="block truncate text-sm font-semibold text-ink dark:text-white">{{ $resume->title }}</span>
            </span>
            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink-faint" />
        </a>
    @endif

    <div class="panel mt-4 overflow-hidden">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($notebooks as $item)
                <div class="group flex items-center gap-2 px-4 py-3" wire:key="nb-row-{{ $item->id }}">
                    @if ($renamingId === $item->id)
                        <form wire:submit="saveRename" class="flex min-w-0 flex-1 items-center gap-2">
                            <input type="text" wire:model="renamingTitle" maxlength="120" autofocus
                                class="input min-w-0 flex-1 py-1.5 text-sm" aria-label="Tên notebook">
                            <button type="submit" class="btn btn-primary shrink-0 px-3 py-1.5 text-xs">Lưu</button>
                            <button type="button" wire:click="cancelRename" class="btn btn-ghost shrink-0 px-3 py-1.5 text-xs">Huỷ</button>
                        </form>
                        @error('renamingTitle') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    @else
                        <a href="{{ route('studio.ai.notebook', ['notebookId' => $item->id]) }}" wire:navigate
                            class="flex min-w-0 flex-1 items-center gap-3 text-left">
                            <x-icon name="book" class="h-5 w-5 shrink-0 text-brand-600 dark:text-brand-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink dark:text-slate-100">{{ $item->title }}</span>
                                <span class="mt-0.5 block truncate text-xs text-ink-faint dark:text-slate-500">{{ $item->subject?->name }} · {{ $item->updated_at->diffForHumans() }}</span>
                            </span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink-faint" />
                        </a>
                        <div class="relative shrink-0" x-data="{ nbMenu: false }" @click.outside="nbMenu = false" @keydown.escape.window="nbMenu = false">
                            <button type="button" @click="nbMenu = ! nbMenu"
                                class="flex min-h-9 min-w-9 items-center justify-center rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 hover:text-ink focus-visible:outline-2 focus-visible:outline-brand-600 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                                :aria-expanded="nbMenu ? 'true' : 'false'" aria-label="Tùy chọn notebook" aria-haspopup="menu">
                                <x-icon name="dots" class="h-4 w-4" />
                            </button>
                            <div x-show="nbMenu" x-cloak role="menu"
                                class="panel absolute bottom-9 right-0 z-40 w-44 overflow-hidden py-1 shadow-lg">
                                <button type="button" wire:click="startRename({{ $item->id }})" @click="nbMenu = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">Đổi tên</button>
                                @if ($deletable)
                                    <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="Xoá notebook “{{ $item->title }}” cùng toàn bộ nguồn, câu hỏi và nội dung đã tạo?" @click="nbMenu = false"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" role="menuitem">Xóa</button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center">
                    <p class="font-serif text-lg font-semibold text-ink dark:text-white">Chưa có sổ tay nào</p>
                    <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Tạo sổ tay đầu tiên để thêm nguồn và hỏi AI.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
