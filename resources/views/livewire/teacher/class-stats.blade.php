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

        @if ($attention->isNotEmpty())
            <section class="panel border-signal/40 dark:border-red-500/30">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Cần chú ý ({{ $attention->count() }})</h2>
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    @foreach ($attention as $item)
                        <a href="{{ route('students.show', $item['student']) }}" wire:navigate
                            class="flex flex-wrap items-center gap-3 px-5 py-3 transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-signal-soft text-sm font-semibold text-signal dark:bg-red-500/10 dark:text-red-300">
                                {{ $item['student']?->initials() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink dark:text-slate-100">{{ $item['student']?->name }}</p>
                                <p class="mt-0.5 text-xs text-signal dark:text-red-400">{{ implode(' · ', $item['reasons']) }}</p>
                            </div>
                            @if ($item['average'] !== null)
                                <span class="tnum shrink-0 text-lg font-semibold text-ink dark:text-white">{{ number_format($item['average'], 0) }}%</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Phân bố điểm thực lực</h2>
                    <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Mỗi học sinh tính một lần theo điểm trung bình của mình.</p>
                </div>
                <div class="space-y-4 p-5">
                    @foreach ($distribution as $bucket)
                        @php $pct = $totalMembers > 0 ? (int) round($bucket['count'] / $totalMembers * 100) : 0; @endphp
                        <div title="{{ $bucket['label'] }}: {{ $bucket['count'] }} bạn ({{ $pct }}%)">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium text-ink dark:text-slate-200">{{ $bucket['label'] }}</span>
                                <span class="tnum shrink-0 text-sm text-ink-soft dark:text-slate-400">
                                    <span class="text-base font-bold text-ink dark:text-white">{{ $bucket['count'] }}</span>
                                    <span class="ml-1">({{ $pct }}%)</span>
                                </span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                <div class="h-full rounded-full {{ $bucket['color'] }} transition-all" style="width: {{ min(100, $pct) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Mức xem bài được giao</h2>
                    <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Di chuột vào hình tròn để xem tỉ lệ.</p>
                </div>
                <div class="flex items-center gap-6 p-5">
                    @php
                        $total = $completion['completed'] + $completion['opened'] + $completion['pending'];
                        $donePct = $total > 0 ? round($completion['completed'] / $total * 100) : 0;
                        $openPct = $total > 0 ? round($completion['opened'] / $total * 100) : 0;
                        $pendingPct = 100 - $donePct - $openPct;
                    @endphp
                    <span class="relative flex h-32 w-32 shrink-0 items-center justify-center rounded-full"
                        style="background: conic-gradient(#16a34a 0 {{ $donePct }}%, #4f46e5 {{ $donePct }}% {{ $donePct + $openPct }}%, #e5e7eb {{ $donePct + $openPct }}% 100%)"
                        role="img" title="Đã xong {{ $donePct }}% · Đang xem {{ $openPct }}% · Chưa mở {{ $pendingPct }}%"
                        aria-label="Đã xong {{ $donePct }}%, đang xem {{ $openPct }}%, chưa mở {{ $pendingPct }}%">
                        <span class="flex h-20 w-20 flex-col items-center justify-center rounded-full bg-white dark:bg-night-800">
                            <span class="tnum text-xl font-bold text-ink dark:text-white">{{ $donePct }}%</span>
                            <span class="text-[10px] text-ink-faint dark:text-slate-500">đã xong</span>
                        </span>
                    </span>
                    <ul class="min-w-0 flex-1 space-y-2.5 text-sm">
                        <li class="flex items-center justify-between gap-3" title="{{ $completion['completed'] }} lượt đã xem xong">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-success"></span>Đã xong</span>
                            <span class="tnum shrink-0 font-bold text-ink dark:text-white">{{ $completion['completed'] }} <span class="text-xs font-normal text-ink-faint">({{ $donePct }}%)</span></span>
                        </li>
                        <li class="flex items-center justify-between gap-3" title="{{ $completion['opened'] }} lượt đang xem dở">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600 dark:bg-brand-400"></span>Đang xem</span>
                            <span class="tnum shrink-0 font-bold text-ink dark:text-white">{{ $completion['opened'] }} <span class="text-xs font-normal text-ink-faint">({{ $openPct }}%)</span></span>
                        </li>
                        <li class="flex items-center justify-between gap-3" title="{{ $completion['pending'] }} lượt chưa mở">
                            <span class="flex items-center gap-2 text-ink-soft dark:text-slate-300"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600"></span>Chưa mở</span>
                            <span class="tnum shrink-0 font-bold text-ink dark:text-white">{{ $completion['pending'] }} <span class="text-xs font-normal text-ink-faint">({{ $pendingPct }}%)</span></span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <section class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Xu hướng điểm lần đầu theo tuần</h2>
                <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Trung bình lần làm đầu các đề nộp trong tuần. Di chuột vào từng điểm để xem số.</p>
            </div>
            <div class="p-5">
                @php
                    $chartW = 640; $chartH = 240; $padL = 36; $padB = 26; $padT = 12;
                    $plotW = $chartW - $padL - 8; $plotH = $chartH - $padT - $padB;
                    $point = function ($value, $i, $n) use ($padL, $padT, $plotW, $plotH) {
                        $x = $n <= 1 ? $padL + $plotW / 2 : $padL + $i * ($plotW / ($n - 1));
                        $y = $padT + $plotH - (min(100, max(0, $value)) / 100) * $plotH;
                        return [$x, $y];
                    };
                    $segments = []; $current = [];
                    foreach ($trend as $i => $week) {
                        if ($week['average'] === null) {
                            if ($current !== []) { $segments[] = $current; $current = []; }
                            continue;
                        }
                        [$x, $y] = $point($week['average'], $i, $trend->count());
                        $current[] = ['x' => $x, 'y' => $y, 'label' => $week['label'], 'value' => $week['average']];
                    }
                    if ($current !== []) { $segments[] = $current; }
                @endphp
                @if ($segments !== [])
                    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" class="w-full" role="img" aria-label="Xu hướng điểm trung bình 8 tuần">
                        @foreach ([0, 25, 50, 75, 100] as $grid)
                            @php [$gx, $gy] = $point($grid, 0, 1); @endphp
                            <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $chartW - 8 }}" y2="{{ $gy }}" stroke="currentColor" stroke-width="1" class="text-rule dark:text-night-700" opacity="0.6" />
                            <text x="{{ $padL - 6 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" class="fill-ink-faint dark:fill-slate-500">{{ $grid }}</text>
                        @endforeach
                        @foreach ($segments as $segment)
                            @php
                                $line = collect($segment)->map(fn ($p) => round($p['x'], 1).','.round($p['y'], 1))->implode(' ');
                                $first = $segment[0]; $last = $segment[count($segment) - 1];
                                $base = $padT + $plotH;
                            @endphp
                            <polygon points="{{ $padL }},{{ $base }} {{ $line }} {{ $last['x'] }},{{ $base }}" fill="#4f46e5" opacity="0.12" />
                            <polyline points="{{ $line }}" fill="none" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($segment as $p)
                                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5" fill="#4f46e5" stroke="#fff" stroke-width="2">
                                    <title>Tuần {{ $p['label'] }}: {{ $p['value'] }}%</title>
                                </circle>
                            @endforeach
                        @endforeach
                        @foreach ($trend as $i => $week)
                            @php [$lx] = $point(0, $i, $trend->count()); @endphp
                            <text x="{{ $lx }}" y="{{ $chartH - 6 }}" text-anchor="middle" font-size="10" class="fill-ink-faint dark:fill-slate-500">{{ $week['label'] }}</text>
                        @endforeach
                    </svg>
                @else
                    <p class="empty">Chưa có bài nộp nào trong 8 tuần qua.</p>
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Điểm trung bình lần đầu từng đề</h2>
                <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Bấm vào đề để sang trang chấm. Đề nào thấp là cả lớp đang đuối ở đó.</p>
            </div>
            <div class="px-5 py-2 text-xs text-ink-faint dark:text-slate-500">
                <span class="mr-4"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-brand-600 dark:bg-brand-400"></span>Lần đầu</span>
                <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-success"></span>Tốt nhất</span>
            </div>
            <div class="space-y-5 p-5 pt-3">
                @forelse ($examStats as $exam)
                    <a href="{{ route('studio.grading', $exam['id']) }}" wire:navigate class="block rounded-[12px] p-2 transition-colors hover:bg-paper-2 dark:hover:bg-white/5"
                        title="{{ $exam['title'] }}: lần đầu {{ $exam['average'] !== null ? $exam['average'].'%' : 'chưa có' }}, tốt nhất {{ $exam['best_average'] !== null ? $exam['best_average'].'%' : 'chưa có' }}, {{ $exam['attempts'] }} lượt làm">
                        <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-2">
                            <span class="min-w-0 truncate text-sm font-medium text-ink dark:text-slate-100">
                                {{ $exam['title'] }}
                                @if ($exam['weight'] != 1)
                                    <span class="chip chip-brand ml-1.5">×{{ rtrim(rtrim(number_format($exam['weight'], 1), '0'), '.') }}</span>
                                @endif
                            </span>
                            <span class="tnum shrink-0 text-xs text-ink-faint">{{ $exam['attempts'] }} lượt</span>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="h-3 flex-1 overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                    <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400 transition-all" style="width: {{ $exam['average'] !== null ? min(100, (int) round($exam['average'])) : 0 }}%"></div>
                                </div>
                                <span class="tnum w-12 shrink-0 text-right text-xs font-semibold text-ink dark:text-white">{{ $exam['average'] !== null ? $exam['average'].'%' : '—' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="h-3 flex-1 overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                    <div class="h-full rounded-full bg-success transition-all" style="width: {{ $exam['best_average'] !== null ? min(100, (int) round($exam['best_average'])) : 0 }}%"></div>
                                </div>
                                <span class="tnum w-12 shrink-0 text-right text-xs text-ink-soft dark:text-slate-400">{{ $exam['best_average'] !== null ? $exam['best_average'].'%' : '—' }}</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="empty">Chưa có đề thi nào trong môn.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lượt nộp 14 ngày qua</h2>
                <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Di chuột vào từng cột để xem số lượt theo ngày.</p>
            </div>
            <div class="p-5">
                @php $peak = max(1, $daily->max('count')); @endphp
                <div class="flex h-40 items-end gap-1.5">
                    @foreach ($daily as $day)
                        <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-1.5 self-stretch" title="{{ $day['label'] }}: {{ $day['count'] }} lượt nộp">
                            <span class="tnum text-[11px] font-semibold {{ $day['count'] > 0 ? 'text-ink dark:text-white' : 'text-ink-faint/50 dark:text-slate-600' }}">{{ $day['count'] > 0 ? $day['count'] : '' }}</span>
                            <div class="w-full rounded-t-[6px] {{ $day['count'] > 0 ? 'bg-brand-600/85 dark:bg-brand-400/85' : 'bg-paper-2 dark:bg-night-700' }}"
                                style="height: {{ max(3, (int) round($day['count'] / $peak * 100)) }}%"></div>
                            <span class="tnum shrink-0 text-[9px] text-ink-faint dark:text-slate-500">{{ $day['label'] }}</span>
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
