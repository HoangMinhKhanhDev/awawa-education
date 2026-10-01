<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="font-serif text-2xl font-semibold text-ink dark:text-white">Hoạt động AI</h1>
        <a href="{{ route('studio.ai') }}" class="btn btn-outline">Quay lại Notebook</a>
    </div>

    <div class="panel panel-pad">
        <p class="tnum text-sm text-ink-soft dark:text-slate-400">
            {{ number_format((int) $summary->calls) }} lượt gọi
            @if ((int) $summary->calls - (int) $summary->successes > 0)
                · <span class="font-medium text-signal dark:text-red-400">{{ number_format((int) $summary->calls - (int) $summary->successes) }} lỗi</span>
            @endif
        </p>
    </div>

    <div class="panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-4 py-3 dark:border-night-700">
            <h2 class="text-sm font-semibold text-ink dark:text-white">Lượt gọi gần đây</h2>
            <select class="input w-auto py-1.5 text-xs" wire:model.live="statusFilter" aria-label="Lọc theo trạng thái">
                <option value="">Mọi trạng thái</option>
                <option value="success">Thành công</option>
                <option value="error">Lỗi</option>
            </select>
        </div>

        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($logs as $log)
                <article class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start" wire:key="ai-log-{{ $log->id }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($log->is_success)
                                <span class="chip chip-signal">Lỗi</span>
                            @endunless
                            <span class="text-sm font-medium text-ink dark:text-slate-100">{{ str($log->purpose)->replace('_', ' ')->title() }}</span>
                            @if ($log->latency_ms)
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ number_format($log->latency_ms / 1000, 1) }} giây</span>
                            @endif
                        </div>
                        @if ($log->error)
                            <p class="mt-1 line-clamp-2 text-xs text-signal dark:text-red-400">{{ $log->error }}</p>
                        @endif
                    </div>
                    <time class="tnum text-xs text-ink-faint dark:text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('d/m/Y H:i') }}">{{ $log->created_at->diffForHumans() }}</time>
                </article>
            @empty
                <p class="empty">Chưa có hoạt động AI phù hợp với bộ lọc.</p>
            @endforelse
        </div>
    </div>
</div>
