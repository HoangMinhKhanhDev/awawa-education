<div class="space-y-7">
    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $greeting }}, {{ $user->name }}</h1>
            <p class="page-sub">
                {{ $user->role->label() }}@if ($currentSubject)<span class="mx-1.5 text-rule-strong dark:text-night-700">/</span><span style="color: {{ $currentSubject->color }}">{{ $currentSubject->name }}</span>@endif
            </p>
        </div>
    </div>

    {{-- Học sinh --}}
    @if ($studentData)
        @if (! $user->isActiveMemberOf($user->subject_id))
            <div class="alert alert-warning">
                Bạn chưa thuộc đội tuyển môn nào. Hiện chỉ xem được tài liệu công khai — liên hệ giáo viên bộ môn để được thêm vào đội và bắt đầu làm bài.
            </div>
        @endif

        @if ($studentData['inProgress']->isNotEmpty())
            <section class="panel border-brand-300 dark:border-brand-500/40">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Bài đang làm dở</h2>
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    @foreach ($studentData['inProgress'] as $exam)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <p class="font-medium text-ink dark:text-slate-100">{{ $exam->title }}</p>
                                <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">{{ $exam->type->label() }} · {{ $exam->exam_questions_count }} câu</p>
                            </div>
                            <a href="{{ route('student.take', $exam) }}" wire:navigate class="btn btn-primary px-3.5 py-2 text-xs">Tiếp tục làm</a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Bài sắp tới</h2>
            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($studentData['available'] as $exam)
                        @php $dueSoon = $exam->due_at && $exam->due_at->diffInHours(now(), false) >= -48; @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-ink dark:text-slate-100">{{ $exam->title }}</p>
                                    <span class="chip chip-neutral">{{ $exam->type->label() }}</span>
                                </div>
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                                    <span class="tnum">{{ $exam->exam_questions_count }} câu</span>
                                    <span class="tnum">{{ (float) $exam->total_points }} điểm</span>
                                    @if ($exam->duration_minutes)<span class="tnum">{{ $exam->duration_minutes }} phút</span>@endif
                                    @if ($exam->due_at)
                                        <span class="{{ $dueSoon ? 'font-medium text-signal' : '' }}">Hạn {{ $exam->due_at->format('d/m H:i') }}</span>
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('student.take', $exam) }}" wire:navigate class="btn btn-outline px-3.5 py-2 text-xs">Làm bài</a>
                        </div>
                    @empty
                        <p class="empty">Không có bài nào đang chờ.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Kết quả gần đây</h2>
            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($studentData['recent'] as $attempt)
                        <a href="{{ route('student.result', $attempt->exam) }}" wire:navigate
                            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                            <div class="min-w-0">
                                <p class="font-medium text-ink dark:text-slate-100">{{ $attempt->exam?->title }}</p>
                                <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                    {{ $attempt->status->label() }}@if ($attempt->submitted_at) · {{ $attempt->submitted_at->format('d/m/Y H:i') }}@endif
                                </p>
                            </div>
                            <span class="tnum text-lg font-semibold text-ink dark:text-white">{{ (float) $attempt->score }}<span class="text-sm font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span></span>
                        </a>
                    @empty
                        <p class="empty">Chưa có kết quả nào.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Tài liệu công khai</h2>
            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($studentData['documents'] as $document)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-ink-faint dark:text-slate-500" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium text-ink dark:text-slate-100">{{ $document->title }}</p>
                                    @if (! $studentData['documentsAreOwnSubject'] && $document->subject)
                                        <span class="chip chip-neutral" style="color: {{ $document->subject->color }}">{{ $document->subject->name }}</span>
                                    @endif
                                    @if ($document->category)
                                        <span class="chip chip-neutral">{{ $document->category }}</span>
                                    @endif
                                </div>
                                <p class="tnum mt-0.5 truncate text-xs text-ink-faint dark:text-slate-500">
                                    {{ $document->original_name }} — {{ $document->sizeForHumans() }}@if ($document->creator)<span class="mx-1.5">—</span>{{ $document->creator->name }}@endif
                                </p>
                            </div>
                            <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="btn btn-outline px-3.5 py-2 text-xs">Mở</a>
                        </div>
                    @empty
                        <p class="empty">Chưa có tài liệu công khai nào.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    {{-- Giáo viên --}}
    @if ($teacherData)
        <section class="panel panel-pad">
            <div class="flex flex-wrap items-end gap-x-10 gap-y-6">
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Bài chờ chấm</p>
                    <p class="stat-num mt-2 {{ $teacherData['pendingGrading'] > 0 ? 'text-signal dark:text-red-400' : '' }}">{{ $teacherData['pendingGrading'] }}</p>
                </div>
                <div class="hidden h-12 w-px bg-rule sm:block dark:bg-night-700"></div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Học sinh trong đội</p>
                    <p class="stat-num mt-2">{{ $teacherData['members'] }}</p>
                </div>
                <div class="hidden h-12 w-px bg-rule sm:block dark:bg-night-700"></div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Đang giao</p>
                    <p class="stat-num mt-2">{{ $teacherData['published'] }}</p>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="divide-y divide-rule dark:divide-night-700">
                <a href="{{ route('studio') }}" wire:navigate class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                    <x-icon name="sparkles" class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink dark:text-slate-100">Studio</p>
                        <p class="mt-0.5 text-sm text-ink-soft dark:text-slate-400">Soạn đề, bài tập, tài liệu và thông báo cho môn của bạn.</p>
                    </div>
                </a>
                <a href="{{ route('students') }}" wire:navigate class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                    <x-icon name="users" class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink dark:text-slate-100">Quản lý học sinh</p>
                        <p class="mt-0.5 text-sm text-ink-soft dark:text-slate-400">Thêm hoặc gỡ học sinh khỏi đội tuyển của môn.</p>
                    </div>
                </a>
            </div>
        </section>
    @endif

    {{-- Quản trị --}}
    @if ($roleCounts)
        <section class="panel panel-pad">
            <div class="flex flex-wrap items-end gap-x-10 gap-y-6">
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Giáo viên</p>
                    <p class="stat-num mt-2">{{ $roleCounts['teacher'] }}</p>
                </div>
                <div class="hidden h-12 w-px bg-rule sm:block dark:bg-night-700"></div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Học sinh</p>
                    <p class="stat-num mt-2">{{ $roleCounts['student'] }}</p>
                </div>
                <div class="hidden h-12 w-px bg-rule sm:block dark:bg-night-700"></div>
                <div>
                    <p class="text-[13px] text-ink-soft dark:text-slate-400">Môn học</p>
                    <p class="stat-num mt-2">{{ $roleCounts['subject'] }}</p>
                </div>
            </div>
        </section>

        <section class="panel">
            <details class="group">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Lộ trình xây dựng</h2>
                    <span class="text-xs text-ink-faint transition-transform group-open:rotate-180 dark:text-slate-500">▾</span>
                </summary>
                <div class="border-t border-rule px-5 py-4 dark:border-night-700">
                    <ul class="space-y-3 text-sm">
                        @php
                            $phases = [
                                ['P0', 'Nền tảng: xác thực, phân quyền, tách biệt môn, PWA', true],
                                ['P1', 'Quản trị: người dùng, phân môn, API key', true],
                                ['P2', 'Studio giáo viên: ngân hàng câu hỏi, đề thi, bài tập, tài liệu', true],
                                ['P3', 'Học sinh: làm bài, chấm điểm, thông tin, xếp hạng, hồ sơ', true],
                                ['P4', 'Sơ đồ kiến thức (bảng trắng + xuất ảnh/PDF/JSON)', true],
                                ['P5', 'AI, thông báo và Web Push, thống kê', true],
                                ['P6', 'Tối ưu hiệu năng và triển khai Hostinger', true],
                            ];
                        @endphp
                        @foreach ($phases as [$code, $label, $done])
                            <li class="flex items-start gap-3">
                                <span class="tnum mt-0.5 w-6 shrink-0 text-xs font-semibold {{ $done ? 'text-success' : 'text-ink-faint dark:text-slate-500' }}">{{ $code }}</span>
                                <span class="{{ $done ? 'text-ink-soft dark:text-slate-300' : 'text-ink-faint dark:text-slate-500' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        </section>
    @endif
</div>
