<div class="space-y-6">
    <x-admin.tabs active="stats" />

    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Thống kê</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tổng quan hoạt động của hệ thống awawa.</p>
    </header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
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
            ['Khóa API', $counts['api_key'].' ('.$counts['api_key_active'].' dùng)'],
            ['Lượt gọi AI', $counts['ai_calls']],
        ] as [$label, $value])
            <div class="card">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Lượt làm bài --}}
        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lượt làm bài (14 ngày)</h2>
            @php $maxAttempt = max(1, collect($attemptSeries)->max('value')); @endphp
            <div class="mt-4 flex h-40 items-end gap-1">
                @foreach ($attemptSeries as $point)
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $point['label'] }}: {{ $point['value'] }}">
                        <div class="w-full rounded-t bg-brand-500" style="height: {{ (int) round(($point['value'] / $maxAttempt) * 100) }}%"></div>
                        <span class="text-[9px] text-slate-400">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lượt gọi AI --}}
        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lượt gọi AI (14 ngày)</h2>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">Hôm nay: {{ $counts['ai_today'] }}</span>
                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">Tổng token: {{ number_format($counts['ai_tokens']) }}</span>
                <span class="badge bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">Lỗi: {{ $counts['ai_errors'] }}</span>
            </div>
            @php $maxAi = max(1, collect($aiSeries)->max('value')); @endphp
            <div class="mt-4 flex h-32 items-end gap-1">
                @foreach ($aiSeries as $point)
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $point['label'] }}: {{ $point['value'] }}">
                        <div class="w-full rounded-t bg-violet-500" style="height: {{ (int) round(($point['value'] / $maxAi) * 100) }}%"></div>
                        <span class="text-[9px] text-slate-400">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Điểm trung bình theo môn</h2>
            <div class="mt-4 space-y-3">
                @forelse ($subjectAverages as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $row['name'] }}</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $row['average'] }} ({{ $row['attempts'] }} bài)</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, (int) round(($row['average'] / 10) * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Chưa có dữ liệu điểm.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Hoạt động AI gần đây</h2>
            <div class="mt-4 space-y-2">
                @forelse ($recentAiLogs as $log)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-white/10">
                        <span class="badge {{ $log->is_success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">
                            {{ $log->is_success ? 'OK' : 'Lỗi' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-slate-700 dark:text-slate-200">
                                {{ $log->purpose ?? 'ai' }} · {{ $log->provider_key ?? '—' }}
                            </p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $log->user?->name ?? 'Hệ thống' }}
                                @if ($log->subject) · {{ $log->subject->name }} @endif
                                · {{ $log->total_tokens }} token · {{ $log->latency_ms }}ms
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Chưa có hoạt động AI.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
