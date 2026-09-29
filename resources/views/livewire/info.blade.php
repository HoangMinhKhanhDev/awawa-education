<div class="space-y-7">
    <div class="page-head">
        <div>
            <h1 class="page-title">Thông tin</h1>
            <p class="page-sub">
                @if ($subject)
                    Thông báo và bảng xếp hạng của môn <span class="font-medium" style="color: {{ $subject->color }}">{{ $subject->name }}</span>.
                @else
                    Tổng quan các môn trong hệ thống.
                @endif
            </p>
        </div>
    </div>

    @if (! $subject)
        <section class="panel panel-pad">
            <h2 class="text-[15px] font-semibold text-ink dark:text-white">Các môn trong hệ thống</h2>
            <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Quản lý chi tiết từng môn trong khu vực quản trị.</p>
            <div class="mt-4 grid gap-x-8 sm:grid-cols-2">
                @foreach ($subjects as $item)
                    <div class="flex items-center gap-3 border-t border-rule py-3 dark:border-night-700">
                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $item->color }}"></span>
                        <span class="text-sm text-ink dark:text-slate-200">{{ $item->name }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Bảng xếp hạng</h2>
            <p class="text-sm text-ink-soft dark:text-slate-400">
                Điểm mỗi bạn là trung bình có trọng số các lần làm đầu từng đề (chỉ tính bài đã chấm xong, đề quan trọng hệ số cao hơn).
                Làm từ 4 đề trở lên thì bỏ 1 đề thấp nhất; bằng điểm thì bạn làm nhiều đề hơn xếp trên.
            </p>

            <div class="panel">
                <div class="grid grid-cols-[1.5rem_1fr_auto_auto] items-center gap-x-4 border-b border-rule px-5 py-2.5 text-xs font-medium text-ink-faint dark:border-night-700 dark:text-slate-500">
                    <span>#</span>
                    <span>Học sinh</span>
                    <span class="w-20 text-right">Điểm TB</span>
                    <span class="w-20 text-right">Tốt nhất</span>
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($leaderboard as $rank => $row)
                        @php $isMe = $row['student']?->id === $user->id; @endphp
                        <div class="grid grid-cols-[1.5rem_1fr_auto_auto] items-center gap-x-4 px-5 py-3.5 {{ $isMe ? 'bg-brand-50/60 dark:bg-brand-500/10' : '' }}">
                            <span class="tnum text-lg font-semibold {{ $rank === 0 ? 'text-brand-700 dark:text-brand-300' : 'text-ink-faint dark:text-slate-500' }}">{{ $rank + 1 }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink dark:text-slate-100">
                                    {{ $row['student']?->name }}
                                    @if ($isMe)<span class="ml-1 text-xs font-normal text-brand-700 dark:text-brand-300">bạn</span>@endif
                                </p>
                                <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">{{ $row['exams'] }} đề · {{ $row['retakes'] }} lượt làm</p>
                            </div>
                            <span class="tnum w-20 text-right text-lg font-semibold text-ink dark:text-white">{{ number_format($row['average'], 0) }}%</span>
                            <span class="tnum w-20 text-right text-sm text-ink-soft dark:text-slate-400">{{ number_format($row['best_percent'], 0) }}%</span>
                        </div>
                    @empty
                        <p class="empty">Chưa có học sinh trong đội tuyển.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Thông báo từ giáo viên</h2>

            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($announcements as $announcement)
                        <article class="px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($announcement->is_pinned)
                                    <span class="chip chip-brand">Đã ghim</span>
                                @endif
                                <h3 class="text-[17px] font-semibold text-ink dark:text-white">{{ $announcement->title }}</h3>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-soft dark:text-slate-300">{{ $announcement->body }}</p>
                            <p class="mt-2.5 text-xs text-ink-faint dark:text-slate-500">
                                {{ $announcement->creator?->name }}
                                @if ($announcement->published_at)
                                    <span class="mx-1.5">—</span>{{ $announcement->published_at->diffForHumans() }}
                                @endif
                            </p>
                        </article>
                    @empty
                        <p class="empty">Chưa có thông báo nào.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
