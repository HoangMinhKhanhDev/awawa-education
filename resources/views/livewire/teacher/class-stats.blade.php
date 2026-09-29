<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Thống kê lớp</h1>
            <p class="page-sub">
                @if ($subject)
                    Toàn cảnh môn <span class="font-medium" style="color: {{ $subject->color }}">{{ $subject->name }}</span>
                @else
                    Bạn chưa được phân môn.
                @endif
            </p>
        </div>
        <a href="{{ route('students') }}" wire:navigate class="btn btn-ghost shrink-0 px-3 py-2 text-xs">Về danh sách</a>
    </div>

    @if ($subject)
        <section class="panel panel-pad">
            <div class="flex flex-wrap items-end gap-x-10 gap-y-6">
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Sĩ số</p>
                    <p class="stat-num mt-2">{{ $totalMembers }}</p>
                </div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Điểm TB cả lớp</p>
                    <p class="stat-num mt-2">{{ $classAverage !== null ? $classAverage.'%' : '—' }}</p>
                </div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Lượt nộp 14 ngày</p>
                    <p class="stat-num mt-2">{{ $daily->sum('count') }}</p>
                </div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Lần rời màn hình</p>
                    <p class="stat-num mt-2 {{ $violations > 0 ? 'text-signal dark:text-red-400' : '' }}">{{ $violations }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Phân bố điểm thực lực</h2>
                </div>
                <div class="space-y-3 p-5">
                    @foreach ($distribution as $bucket)
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-sm">
                                <span class="text-ink-soft dark:text-slate-300">{{ $bucket['label'] }}</span>
                                <span class="tnum font-semibold text-ink dark:text-white">{{ $bucket['count'] }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                <div class="h-full rounded-full {{ $bucket['color'] }}" style="width: {{ $totalMembers > 0 ? min(100, (int) round($bucket['count'] / $totalMembers * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Mức xem bài được giao</h2>
                </div>
                <div class="flex items-center gap-5 p-5">
                    @php
                        $total = $completion['completed'] + $completion['opened'] + $completion['pending'];
                        $donePct = $total > 0 ? $completion['completed'] / $total * 100 : 0;
                        $openPct = $total > 0 ? $completion['opened'] / $total * 100 : 0;
                    @endphp
                    <span class="h-24 w-24 shrink-0 rounded-full"
                        style="background: conic-gradient(var(--color-success, #16a34a) 0 {{ $donePct }}%, var(--color-brand-600, #4f46e5) {{ $donePct }}% {{ $donePct + $openPct }}%, var(--color-paper-2, #e5e7eb) {{ $donePct + $openPct }}% 100%)"
                        role="img" aria-label="Đã xong {{ (int) round($donePct) }}%"></span>
                    <ul class="min-w-0 flex-1 space-y-2 text-sm">
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 rounded-full bg-success"></span>Đã xong</span>
                            <span class="tnum font-semibold text-ink dark:text-white">{{ $completion['completed'] }}</span>
                        </li>
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 rounded-full bg-brand-600 dark:bg-brand-400"></span>Đang xem</span>
                            <span class="tnum font-semibold text-ink dark:text-white">{{ $completion['opened'] }}</span>
                        </li>
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 rounded-full bg-paper-2 dark:bg-white/10"></span>Chưa mở</span>
                            <span class="tnum font-semibold text-ink dark:text-white">{{ $completion['pending'] }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <section class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Điểm trung bình lần đầu từng đề</h2>
            </div>
            <div class="space-y-4 p-5">
                @forelse ($examStats as $exam)
                    <div>
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate font-medium text-ink dark:text-slate-100">
                                {{ $exam['title'] }}
                                @if ($exam['weight'] != 1)
                                    <span class="chip chip-brand ml-1.5">×{{ rtrim(rtrim(number_format($exam['weight'], 1), '0'), '.') }}</span>
                                @endif
                            </span>
                            <span class="tnum shrink-0 text-ink-soft dark:text-slate-400">
                                {{ $exam['average'] !== null ? $exam['average'].'%' : 'chưa có bài' }} · {{ $exam['attempts'] }} lượt
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                            <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $exam['average'] !== null ? min(100, (int) round($exam['average'])) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="empty">Chưa có đề thi nào trong môn.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lượt nộp 14 ngày qua</h2>
            </div>
            <div class="p-5">
                @php $peak = max(1, $daily->max('count')); @endphp
                <div class="flex h-28 items-end gap-1.5">
                    @foreach ($daily as $day)
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1" title="{{ $day['label'] }}: {{ $day['count'] }} lượt">
                            <div class="w-full rounded-t-[4px] bg-brand-600/80 dark:bg-brand-400/80" style="height: {{ max(2, (int) round($day['count'] / $peak * 100)) }}%"></div>
                            <span class="tnum text-[9px] text-ink-faint dark:text-slate-500">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @else
        <div class="panel">
            <p class="empty">Thống kê hoạt động khi tài khoản giáo viên được phân một môn. Liên hệ quản trị viên để được phân môn.</p>
        </div>
    @endif
</div>
