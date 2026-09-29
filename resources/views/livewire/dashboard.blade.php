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

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Học sinh --}}
    @if ($studentData)
        @if (! $user->isActiveMemberOf($user->subject_id))
            <div class="alert alert-warning">
                Bạn chưa thuộc đội tuyển môn nào. Hiện chỉ xem được tài liệu công khai — liên hệ giáo viên bộ môn để được thêm vào đội và bắt đầu làm bài.
            </div>
        @endif

        {{-- Khối 1: việc gấp nhất --}}
        @if ($studentData['nextUp'] !== null)
            @php $next = $studentData['nextUp']; @endphp
            <section class="panel {{ $next['overdue'] ? 'border-signal/40 dark:border-red-500/30' : 'border-brand-300 dark:border-brand-500/40' }}">
                <div class="flex flex-wrap items-center gap-4 px-5 py-5">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            @if ($next['overdue'])
                                <span class="chip chip-signal">Trễ hạn</span>
                            @else
                                <span class="chip chip-brand">Tiếp theo</span>
                            @endif
                        </p>
                        <p class="mt-1.5 truncate text-lg font-semibold text-ink dark:text-white">{{ $next['title'] }}</p>
                        @if ($next['hint'])
                            <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">{{ $next['hint'] }}</p>
                        @endif
                    </div>
                    @if ($next['kind'] === 'assignment')
                        <button type="button" wire:click="openAssignment({{ $next['id'] }})" class="btn btn-primary shrink-0 px-5 py-2.5">Mở ngay</button>
                    @else
                        <a href="{{ route('student.take', $next['id']) }}" wire:navigate class="btn btn-primary shrink-0 px-5 py-2.5">
                            {{ $next['kind'] === 'exam' ? 'Tiếp tục làm' : 'Làm bài' }}
                        </a>
                    @endif
                </div>
            </section>
        @elseif ($user->isActiveMemberOf($user->subject_id))
            <section class="panel">
                <p class="empty">Xong hết rồi! Xem lại tài liệu bên dưới để ôn tập.</p>
            </section>
        @endif

        {{-- Khối 2: số của tôi --}}
        @if ($studentData['myStats']['exams'] > 0)
            <section class="panel panel-pad">
                <div class="flex flex-wrap items-end gap-x-10 gap-y-4">
                    <div>
                        <p class="text-[13px] text-ink-soft dark:text-slate-400">Điểm thực lực</p>
                        <p class="stat-num mt-2">{{ number_format($studentData['myStats']['average'], 0) }}%</p>
                    </div>
                    @if ($studentData['myStats']['rank'] !== null)
                        <div>
                            <p class="text-[13px] text-ink-soft dark:text-slate-400">Hạng trong đội</p>
                            <p class="stat-num mt-2">{{ $studentData['myStats']['rank'] }}<span class="text-base font-normal text-ink-faint">/{{ $studentData['myStats']['members'] }}</span></p>
                        </div>
                    @endif
                    <div>
                        <p class="text-[13px] text-ink-soft dark:text-slate-400">Số đề đã làm</p>
                        <p class="stat-num mt-2">{{ $studentData['myStats']['exams'] }}</p>
                    </div>
                    <a href="{{ route('info') }}" wire:navigate class="btn btn-ghost ml-auto px-3 py-2 text-xs">Xem bảng xếp hạng</a>
                </div>
            </section>
        @elseif ($user->isActiveMemberOf($user->subject_id))
            <section class="panel">
                <p class="empty">Làm bài đầu tiên để có điểm thực lực của riêng bạn.</p>
            </section>
        @endif

        {{-- Khối 3: việc cần làm --}}
        @if ($studentData['assignmentCards']->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Việc cần làm</h2>
                <div class="panel">
                    <div class="divide-y divide-rule dark:divide-night-700">
                        @foreach ($studentData['assignmentCards'] as $card)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="dash-assign-{{ $card['assignment']->id }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                        <x-icon :name="$card['type']?->icon() ?? 'doc'" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink dark:text-slate-100">
                                            {{ $card['assignment']->assignable?->title ?? '(Nội dung đã bị xoá)' }}
                                            @if ($card['overdue'])
                                                <span class="chip chip-signal ml-1.5">Trễ hạn</span>
                                            @elseif ($card['opened'])
                                                <span class="chip chip-brand ml-1.5">Đang xem</span>
                                            @endif
                                        </p>
                                        <p class="tnum mt-0.5 flex flex-wrap items-center gap-x-2.5 text-xs text-ink-faint dark:text-slate-500">
                                            <span>{{ $card['type']?->label() }}</span>
                                            @if ($card['assignment']->due_at)
                                                <span>Hạn {{ $card['assignment']->due_at->format('d/m') }}@if (! $card['overdue'] && $card['daysLeft'] !== null && $card['daysLeft'] >= 0) (còn {{ $card['daysLeft'] }} ngày)@endif</span>
                                            @endif
                                        </p>
                                        @if ($card['assignment']->note)
                                            <p class="mt-1 text-sm text-ink-soft dark:text-slate-300">{{ $card['assignment']->note }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($card['action'] === 'done')
                                    <button type="button" wire:click="markAssignmentDone({{ $card['assignment']->id }})" class="btn btn-primary shrink-0 px-3.5 py-2 text-xs">
                                        Đã xem xong
                                    </button>
                                @else
                                    <button type="button" wire:click="openAssignment({{ $card['assignment']->id }})" class="btn btn-primary shrink-0 px-3.5 py-2 text-xs">
                                        Mở
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($studentData['inProgressCards']->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Bài đang làm dở</h2>
                <div class="panel">
                    <div class="divide-y divide-rule dark:divide-night-700">
                        @foreach ($studentData['inProgressCards'] as $card)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="dash-doing-{{ $card['exam']->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-ink dark:text-slate-100">{{ $card['exam']->title }}</p>
                                    <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                        Đã trả lời {{ $card['answered'] }}/{{ $card['total'] }} câu
                                        @if ($card['minutesLeft'] !== null)<span class="mx-1.5">·</span>còn {{ $card['minutesLeft'] }} phút @endif
                                    </p>
                                    <div class="mt-2 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                        <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $card['percent'] }}%"></div>
                                    </div>
                                </div>
                                <a href="{{ route('student.take', $card['exam']) }}" wire:navigate class="btn btn-primary shrink-0 px-3.5 py-2 text-xs">Tiếp tục làm</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($studentData['available']->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Bài sắp tới</h2>
                <div class="panel">
                    <div class="divide-y divide-rule dark:divide-night-700">
                        @foreach ($studentData['available'] as $row)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="dash-avail-{{ $row['exam']->id }}">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-ink dark:text-slate-100">{{ $row['exam']->title }}</p>
                                        <span class="chip chip-neutral">{{ $row['exam']->type->label() }}</span>
                                        @if ($row['exam']->allowsRetake() && $row['remaining'] < $row['exam']->maxAttempts())
                                            <span class="chip chip-brand">Còn {{ $row['remaining'] }} lượt</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                                        <span class="tnum">{{ $row['exam']->exam_questions_count }} câu</span>
                                        <span class="tnum">{{ (float) $row['exam']->total_points }} điểm</span>
                                        @if ($row['exam']->duration_minutes)<span class="tnum">{{ $row['exam']->duration_minutes }} phút</span>@endif
                                        @if ($row['exam']->due_at)
                                            <span class="{{ $row['dueSoon'] ? 'font-medium text-signal' : '' }}">Hạn {{ $row['exam']->due_at->format('d/m H:i') }}</span>
                                        @endif
                                    </p>
                                </div>
                                <a href="{{ route('student.take', $row['exam']) }}" wire:navigate class="btn btn-outline px-3.5 py-2 text-xs">Làm bài</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($studentData['recent']->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Kết quả gần đây</h2>
                <div class="panel">
                    <div class="divide-y divide-rule dark:divide-night-700">
                        @foreach ($studentData['recent'] as $attempt)
                            <a href="{{ route('student.result', $attempt->exam) }}" wire:navigate
                                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-paper-2 dark:hover:bg-white/5" wire:key="dash-recent-{{ $attempt->id }}">
                                <div class="min-w-0">
                                    <p class="font-medium text-ink dark:text-slate-100">{{ $attempt->exam?->title }}</p>
                                    <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                        {{ $attempt->status->label() }}@if ($attempt->attempt_no > 1) · Lần {{ $attempt->attempt_no }}@endif@if ($attempt->submitted_at) · {{ $attempt->submitted_at->format('d/m/Y H:i') }}@endif
                                    </p>
                                </div>
                                <span class="tnum text-lg font-semibold text-ink dark:text-white">{{ (float) $attempt->score }}<span class="text-sm font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Tài liệu công khai</h2>
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <input type="search" class="input py-1.5 text-sm" wire:model.live.debounce.400ms="docSearch" placeholder="Tìm tài liệu theo tên" aria-label="Tìm tài liệu">
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($studentData['documents'] as $document)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="dash-doc-{{ $document->id }}">
                            <x-icon name="doc" class="h-4 w-4 shrink-0 text-ink-faint dark:text-slate-500" />
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
                            <a href="{{ $document->viewerUrl() }}" @if (! $document->isViewable()) target="_blank" rel="noopener" @endif wire:navigate class="btn btn-outline px-3.5 py-2 text-xs">Mở</a>
                        </div>
                    @empty
                        <p class="empty">{{ trim($docSearch) !== '' ? 'Không tìm thấy tài liệu nào.' : 'Chưa có tài liệu công khai nào.' }}</p>
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
