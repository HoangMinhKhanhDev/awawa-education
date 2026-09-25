<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Thông báo</h1>
            <p class="page-sub">Toàn bộ thông báo gửi tới bạn.</p>
        </div>
        <button type="button" wire:click="markAllRead" class="btn btn-outline px-3.5 py-2 text-xs">Đánh dấu tất cả đã đọc</button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($notifications as $notification)
                @php $unread = $notification->read_at === null; @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 px-5 py-4 text-left transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $unread ? 'bg-brand-600 dark:bg-brand-400' : 'bg-rule-strong dark:bg-night-700' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium {{ $unread ? 'text-ink dark:text-white' : 'text-ink-soft dark:text-slate-300' }}">
                            {{ $notification->data['title'] ?? 'Thông báo' }}
                        </span>
                        <span class="mt-1 block whitespace-pre-line text-sm leading-relaxed text-ink-soft dark:text-slate-400">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="tnum mt-1.5 block text-xs text-ink-faint dark:text-slate-500">{{ $notification->created_at?->format('d/m/Y H:i') }}</span>
                    </span>
                </button>
            @empty
                <p class="empty">Chưa có thông báo nào.</p>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div>{{ $notifications->links() }}</div>
    @endif
</div>
