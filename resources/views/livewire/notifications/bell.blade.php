<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.60s>
    <button type="button" @click="open = ! open"
        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5"
        aria-label="Thông báo">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition
        class="absolute right-0 z-40 mt-2 w-80 max-w-[85vw] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-night-800">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/5">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Thông báo</p>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Đánh dấu đã đọc
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($recent as $notification)
                @php $unread = $notification->read_at === null; @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')"
                    class="flex w-full gap-2 border-b border-slate-100 px-4 py-3 text-left last:border-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $unread ? 'bg-brand-500' : 'bg-transparent' }}"></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $notification->data['title'] ?? 'Thông báo' }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500 dark:text-slate-400">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-400">Chưa có thông báo nào.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" wire:navigate
            class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-brand-600 hover:bg-slate-50 dark:border-white/5 dark:text-brand-400 dark:hover:bg-white/5">
            Xem tất cả
        </a>
    </div>
</div>
