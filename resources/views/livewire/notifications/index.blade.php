<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Thông báo</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tất cả thông báo của bạn.</p>
        </div>
        <button type="button" wire:click="markAllRead" class="btn btn-outline px-3 py-1.5 text-xs">Đánh dấu tất cả đã đọc</button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse ($notifications as $notification)
                @php $unread = $notification->read_at === null; @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 p-4 text-left transition hover:bg-slate-50 dark:hover:bg-white/5">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $unread ? 'bg-brand-500' : 'bg-slate-200 dark:bg-white/10' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium {{ $unread ? 'text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300' }}">
                            {{ $notification->data['title'] ?? 'Thông báo' }}
                        </span>
                        <span class="mt-1 block whitespace-pre-line text-sm text-slate-500 dark:text-slate-400">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at?->format('d/m/Y H:i') }}</span>
                    </span>
                </button>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Chưa có thông báo nào.</div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div>{{ $notifications->links() }}</div>
    @endif
</div>
