<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Thông tin</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            @if ($subject)
                Thông báo và bảng xếp hạng của môn <span class="font-semibold" style="color: {{ $subject->color }}">{{ $subject->name }}</span>.
            @else
                Tổng quan hệ thống.
            @endif
        </p>
    </header>

    @if (! $subject)
        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Các môn trong hệ thống</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Chọn một môn để quản lý trong khu vực quản trị.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($subjects as $item)
                    <div class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 dark:border-white/10">
                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item->color }}"></span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $item->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Bảng xếp hạng --}}
        <div class="card">
            <div class="flex items-center gap-2">
                <x-icon name="chart" class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Bảng xếp hạng điểm tổng</h2>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tổng điểm các bài đã nộp và đã chấm trong môn.</p>

            <div class="mt-4 space-y-2">
                @forelse ($leaderboard as $rank => $row)
                    <div class="flex items-center gap-3 rounded-xl border p-3 dark:border-white/10
                        {{ $row['student']?->id === $user->id ? 'border-brand-300 bg-brand-50/50 dark:border-brand-500/40 dark:bg-brand-500/10' : 'border-slate-200' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                            {{ $rank === 0 ? 'bg-amber-400 text-amber-900' : ($rank === 1 ? 'bg-slate-300 text-slate-700' : ($rank === 2 ? 'bg-orange-300 text-orange-900' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300')) }}">
                            {{ $rank + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ $row['student']?->name }}
                                @if ($row['student']?->id === $user->id) <span class="text-xs font-normal text-brand-600 dark:text-brand-400">(bạn)</span> @endif
                            </p>
                            <p class="text-xs text-slate-400">{{ $row['attempts'] }} bài đã nộp</p>
                        </div>
                        <span class="font-bold text-brand-600 dark:text-brand-400">{{ $row['total'] }} điểm</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Chưa có học sinh trong đội tuyển.</p>
                @endforelse
            </div>
        </div>

        {{-- Thông báo --}}
        <div class="card">
            <div class="flex items-center gap-2">
                <x-icon name="bell" class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thông báo từ giáo viên</h2>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($announcements as $announcement)
                    <article class="rounded-xl border border-slate-200 p-4 dark:border-white/10" wire:key="ann-{{ $announcement->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($announcement->is_pinned)
                                <span class="badge bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Ghim</span>
                            @endif
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $announcement->title }}</h3>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $announcement->body }}</p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ $announcement->creator?->name }}
                            @if ($announcement->published_at) · {{ $announcement->published_at->diffForHumans() }} @endif
                        </p>
                    </article>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Chưa có thông báo nào.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
