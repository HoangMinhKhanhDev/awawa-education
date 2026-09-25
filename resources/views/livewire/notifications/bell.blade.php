<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.60s>
    <button type="button" @click="open = ! open"
        class="relative flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
        aria-label="Thông báo">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span class="tnum absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-signal px-1 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        class="panel absolute right-0 z-40 mt-2 w-80 max-w-[85vw] overflow-hidden">
        <div class="flex items-center justify-between border-b border-rule px-4 py-3 dark:border-night-700">
            <p class="text-sm font-semibold text-ink dark:text-white">Thông báo</p>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">
                    Đánh dấu đã đọc
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($recent as $notification)
                @php $unread = $notification->read_at === null; @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')"
                    class="flex w-full gap-3 border-b border-rule px-4 py-3 text-left transition-colors last:border-0 hover:bg-paper-2 dark:border-night-700 dark:hover:bg-white/5">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $unread ? 'bg-brand-600' : 'bg-transparent' }}"></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-ink dark:text-slate-100">{{ $notification->data['title'] ?? 'Thông báo' }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-xs leading-relaxed text-ink-soft dark:text-slate-400">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-[11px] text-ink-faint dark:text-slate-500">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="empty">Chưa có thông báo nào.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" wire:navigate
            class="block border-t border-rule px-4 py-2.5 text-center text-xs font-medium text-brand-700 transition-colors hover:bg-paper-2 dark:border-night-700 dark:text-brand-300 dark:hover:bg-white/5">
            Xem tất cả
        </a>
    </div>
</div>
