<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm text-ink-faint dark:text-slate-500">Notebook</p>
            <h1 class="font-serif text-2xl font-semibold text-ink dark:text-white">Hoạt động AI</h1>
            <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Lịch sử gọi AI của riêng bạn, tối đa 100 lượt gần nhất.</p>
        </div>
        <a href="{{ route('studio.ai') }}" class="btn btn-outline">Quay lại Notebook</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="panel panel-pad">
            <p class="text-xs text-ink-faint dark:text-slate-500">Tổng lượt gọi</p>
            <p class="tnum mt-1 text-2xl font-semibold text-ink dark:text-white">{{ number_format((int) $summary->calls) }}</p>
        </div>
        <div class="panel panel-pad">
            <p class="text-xs text-ink-faint dark:text-slate-500">Thành công</p>
            <p class="tnum mt-1 text-2xl font-semibold text-success">{{ number_format((int) $summary->successes) }}</p>
        </div>
        <div class="panel panel-pad">
            <p class="text-xs text-ink-faint dark:text-slate-500">Token đã ghi nhận</p>
            <p class="tnum mt-1 text-2xl font-semibold text-ink dark:text-white">{{ number_format((int) $summary->tokens) }}</p>
        </div>
    </div>

    <div class="panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-4 py-3 dark:border-night-700">
            <h2 class="text-sm font-semibold text-ink dark:text-white">Lượt gọi gần đây</h2>
            <div class="flex gap-2">
                <select class="input w-auto py-1.5 text-xs" wire:model.live="purposeFilter" aria-label="Lọc theo mục đích">
                    <option value="">Mọi mục đích</option>
                    <option value="chat">Trò chuyện</option>
                    <option value="artifact">Tạo nội dung</option>
                    <option value="web_source_picker">Chọn nguồn web</option>
                </select>
                <select class="input w-auto py-1.5 text-xs" wire:model.live="statusFilter" aria-label="Lọc theo trạng thái">
                    <option value="">Mọi trạng thái</option>
                    <option value="success">Thành công</option>
                    <option value="error">Lỗi</option>
                </select>
            </div>
        </div>

        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($logs as $log)
                <article class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start" wire:key="ai-log-{{ $log->id }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip {{ $log->is_success ? 'chip-success' : 'chip-signal' }}">{{ $log->is_success ? 'Thành công' : 'Lỗi' }}</span>
                            <span class="text-sm font-medium text-ink dark:text-slate-100">{{ $log->provider_key ?: 'AI' }}</span>
                            @if ($log->model)
                                <span class="truncate text-xs text-ink-faint dark:text-slate-500">{{ $log->model }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-ink-soft dark:text-slate-400">{{ str($log->purpose)->replace('_', ' ')->title() }}
                            @if ($log->total_tokens) · {{ number_format($log->total_tokens) }} token @endif
                            @if ($log->latency_ms) · {{ number_format($log->latency_ms / 1000, 1) }} giây @endif
                        </p>
                        @if ($log->error)
                            <p class="mt-1 line-clamp-2 text-xs text-signal dark:text-red-400">{{ $log->error }}</p>
                        @endif
                    </div>
                    <time class="tnum text-xs text-ink-faint dark:text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i') }}</time>
                </article>
            @empty
                <p class="empty">Chưa có hoạt động AI phù hợp với bộ lọc.</p>
            @endforelse
        </div>
    </div>
</div>
