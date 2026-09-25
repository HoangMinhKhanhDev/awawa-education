<div class="space-y-6">
    <x-admin.tabs active="stats" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Thống kê</h1>
            <p class="page-sub">Tổng quan hoạt động của hệ thống awawa.</p>
        </div>
    </div>

    <div class="panel panel-pad">
        <div class="grid grid-cols-2 gap-x-8 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([
                ['Giáo viên', $counts['teacher']],
                ['Học sinh', $counts['student']],
                ['Môn học', $counts['subject']],
                ['Câu hỏi', $counts['question']],
                ['Đề thi', $counts['exam']],
                ['Bài tập', $counts['assignment']],
                ['Lượt làm bài', $counts['attempt']],
                ['Đã chấm', $counts['graded']],
                ['Tài liệu', $counts['document']],
                ['Sơ đồ', $counts['map']],
                ['Khóa API', $counts['api_key']],
                ['Lượt gọi AI', $counts['ai_calls']],
            ] as [$label, $value])
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">{{ $label }}</p>
                    <p class="stat-num mt-1.5">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="panel panel-pad">
            <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lượt làm bài, 14 ngày gần nhất</h2>
            @php $maxAttempt = max(1, collect($attemptSeries)->max('value')); @endphp
            <div class="mt-5 flex h-40 items-end gap-1">
                @foreach ($attemptSeries as $point)
                    <div class="flex flex-1 flex-col items-center gap-1.5" title="{{ $point['label'] }}: {{ $point['value'] }}">
                        <div class="w-full rounded-t-[3px] bg-ink/80 dark:bg-slate-300/70" style="height: {{ (int) round(($point['value'] / $maxAttempt) * 100) }}%"></div>
                        <span class="tnum text-[9px] text-ink-faint dark:text-slate-500">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel panel-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lượt gọi AI, 14 ngày gần nhất</h2>
                <div class="flex flex-wrap gap-1.5">
                    <span class="chip chip-neutral">Hôm nay {{ $counts['ai_today'] }}</span>
                    <span class="tnum chip chip-neutral">{{ number_format($counts['ai_tokens']) }} token</span>
                    @if ($counts['ai_errors'] > 0)
                        <span class="chip chip-signal">{{ $counts['ai_errors'] }} lỗi</span>
                    @endif
                </div>
            </div>
            @php $maxAi = max(1, collect($aiSeries)->max('value')); @endphp
            <div class="mt-5 flex h-32 items-end gap-1">
                @foreach ($aiSeries as $point)
                    <div class="flex flex-1 flex-col items-center gap-1.5" title="{{ $point['label'] }}: {{ $point['value'] }}">
                        <div class="w-full rounded-t-[3px] bg-brand-600 dark:bg-brand-400" style="height: {{ (int) round(($point['value'] / $maxAi) * 100) }}%"></div>
                        <span class="tnum text-[9px] text-ink-faint dark:text-slate-500">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Điểm trung bình theo môn</h2>
            </div>
            <div class="space-y-4 p-5">
                @forelse ($subjectAverages as $row)
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-ink dark:text-slate-200">{{ $row['name'] }}</span>
                            <span class="tnum text-ink-soft dark:text-slate-400">{{ $row['average'] }} điểm — {{ $row['attempts'] }} bài</span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                            <div class="h-full rounded-full bg-success" style="width: {{ min(100, (int) round(($row['average'] / 10) * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="empty">Chưa có dữ liệu điểm.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Hoạt động AI gần đây</h2>
            </div>
            <div class="divide-y divide-rule dark:divide-night-700">
                @forelse ($recentAiLogs as $log)
                    <div class="flex items-center gap-4 px-5 py-3.5">
                        <span class="chip {{ $log->is_success ? 'chip-success' : 'chip-signal' }} shrink-0">{{ $log->is_success ? 'OK' : 'Lỗi' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink dark:text-slate-200">{{ $log->purpose ?? 'ai' }} — {{ $log->provider_key ?? '—' }}</p>
                            <p class="tnum truncate text-xs text-ink-faint dark:text-slate-500">
                                {{ $log->user?->name ?? 'Hệ thống' }}@if ($log->subject)<span class="mx-1.5">—</span>{{ $log->subject->name }}@endif<span class="mx-1.5">—</span>{{ $log->total_tokens }} token — {{ $log->latency_ms }}ms
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-ink-faint dark:text-slate-500">{{ $log->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="empty">Chưa có hoạt động AI.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
