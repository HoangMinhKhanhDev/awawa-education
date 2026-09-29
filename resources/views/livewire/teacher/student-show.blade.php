<div class="space-y-6">
    <div class="page-head">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-600 text-base font-semibold text-white">
                {{ $student->initials() }}
            </span>
            <div class="min-w-0">
                <h1 class="page-title truncate">{{ $student->name }}</h1>
                <p class="page-sub truncate">{{ $student->email }}</p>
            </div>
        </div>
        <a href="{{ route('students') }}" wire:navigate class="btn btn-ghost shrink-0 px-3 py-2 text-xs">Về danh sách</a>
    </div>

    <section class="panel panel-pad">
        <div class="flex flex-wrap items-end gap-x-10 gap-y-6">
            <div>
                <p class="text-[13px] text-ink-soft dark:text-slate-400">Điểm thực lực</p>
                <p class="stat-num mt-2">{{ $ability ? number_format($ability['average'], 0).'%' : '—' }}</p>
            </div>
            @if ($rank !== null)
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Hạng trong đội</p>
                    <p class="stat-num mt-2">{{ $rank }}</p>
                </div>
            @endif
            <div>
                <p class="text-[13px] text-ink-soft dark:text-slate-400">Số đề đã làm</p>
                <p class="stat-num mt-2">{{ $ability['exams'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[13px] text-ink-soft dark:text-slate-400">Tổng lượt làm</p>
                <p class="stat-num mt-2">{{ $ability['retakes'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[13px] text-ink-soft dark:text-slate-400">Lần rời màn hình</p>
                <p class="stat-num mt-2">{{ $attempts->sum(fn ($attempt) => count($attempt->anti_cheat ?? [])) }}</p>
            </div>
        </div>
        <p class="mt-4 text-xs text-ink-faint dark:text-slate-500">Điểm thực lực là trung bình có trọng số các lần làm đầu từng đề (chỉ bài đã chấm xong).</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-ink dark:text-white">Điểm theo đề</h2>
        <div class="panel">
            <div class="divide-y divide-rule dark:divide-night-700">
                @forelse ($ability['details'] ?? [] as $row)
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink dark:text-slate-100">
                                {{ $row['exam_title'] }}
                                @if ($row['weight'] != 1)
                                    <span class="chip chip-brand ml-1.5">×{{ rtrim(rtrim(number_format($row['weight'], 1), '0'), '.') }}</span>
                                @endif
                            </p>
                            <div class="mt-2 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ min(100, (int) round($row['first_percent'] ?? 0)) }}%"></div>
                            </div>
                        </div>
                        <div class="tnum shrink-0 text-right text-xs text-ink-soft dark:text-slate-400">
                            <p>Lần đầu <span class="font-semibold text-ink dark:text-white">{{ $row['first_percent'] !== null ? number_format($row['first_percent'], 0).'%' : '—' }}</span></p>
                            <p class="mt-0.5">Tốt nhất {{ number_format($row['best_percent'], 0) }}% · {{ $row['attempts'] }} lượt</p>
                        </div>
                        <a href="{{ route('studio.grading', $row['exam_id']) }}" wire:navigate class="btn btn-outline shrink-0 px-3 py-1.5 text-xs">Chấm</a>
                    </div>
                @empty
                    <p class="empty">Học sinh chưa có bài làm nào đã chấm.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-ink dark:text-white">Các lần nộp gần đây</h2>
        <div class="panel">
            <div class="divide-y divide-rule dark:divide-night-700">
                @forelse ($attempts as $attempt)
                    @php
                        $violations = count($attempt->anti_cheat ?? []);
                        $late = $attempt->expires_at !== null && $attempt->submitted_at !== null && $attempt->submitted_at->greaterThan($attempt->expires_at);
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink dark:text-slate-100">
                                {{ $attempt->exam?->title ?? '(đề đã xoá)' }}
                                <span class="tnum ml-1.5 text-xs font-normal text-ink-faint dark:text-slate-500">lần {{ $attempt->attempt_no }}</span>
                            </p>
                            <p class="tnum mt-0.5 flex flex-wrap items-center gap-x-2.5 text-xs text-ink-faint dark:text-slate-500">
                                @if ($attempt->submitted_at)<span>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</span>@endif
                                @if ($attempt->time_spent_seconds !== null)<span>{{ (int) round($attempt->time_spent_seconds / 60) }} phút</span>@endif
                                @if ($violations > 0)<span class="font-medium text-signal dark:text-red-400">{{ $violations }} lần rời màn hình</span>@endif
                                @if ($late)<span class="font-medium text-signal dark:text-red-400">Nộp trễ</span>@endif
                                @if ($attempt->status === \App\Enums\AttemptStatus::Submitted)<span class="text-warning dark:text-amber-300">Chờ chấm</span>@endif
                            </p>
                        </div>
                        <span class="tnum shrink-0 text-lg font-semibold text-ink dark:text-white">{{ (float) $attempt->score }}<span class="text-sm font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span></span>
                    </div>
                @empty
                    <p class="empty">Chưa có lần nộp nào.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-ink dark:text-white">Nội dung được giao</h2>
        <div class="panel">
            <div class="divide-y divide-rule dark:divide-night-700">
                @forelse ($receipts as $receipt)
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink dark:text-slate-100">{{ $receipt->assignment?->assignable?->title ?? '(nội dung đã xoá)' }}</p>
                            <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                @if ($receipt->completed_at)Xem xong {{ $receipt->completed_at->format('d/m H:i') }}
                                @elseif ($receipt->opened_at)Đã mở {{ $receipt->opened_at->format('d/m H:i') }}
                                @elseif ($receipt->delivered_at)Chưa mở
                                @else Chưa nhận @endif
                            </p>
                        </div>
                        <span class="chip shrink-0 {{ $receipt->isCompleted() ? 'chip-success' : ($receipt->isOpened() ? 'chip-brand' : 'chip-neutral') }}">
                            {{ $receipt->isCompleted() ? 'Đã xong' : ($receipt->isOpened() ? 'Đang xem' : 'Chưa mở') }}
                        </span>
                    </div>
                @empty
                    <p class="empty">Chưa được giao nội dung nào.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
