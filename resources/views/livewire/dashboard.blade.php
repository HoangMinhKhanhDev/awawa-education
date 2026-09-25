<div class="space-y-6">
    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
    @endphp
    <header>
        <p class="text-sm font-medium text-brand-600 dark:text-brand-400">{{ $greeting }}, {{ $user->name }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Trang chủ</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Bạn đang truy cập với vai trò
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $user->role->label() }}</span>
            @if ($currentSubject)
                · môn <span class="font-semibold" style="color: {{ $currentSubject->color }}">{{ $currentSubject->name }}</span>
            @endif
        </p>
    </header>

    {{-- Học sinh --}}
    @if ($studentData)
        @if ($user->isStudent() && ! $user->isActiveMemberOf($user->subject_id))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <p class="font-semibold">Bạn chưa thuộc đội tuyển môn nào</p>
                <p class="mt-1">Hiện bạn chỉ có thể xem tài liệu công khai. Hãy liên hệ giáo viên bộ môn để được thêm vào đội tuyển và bắt đầu làm bài, thi và tính điểm.</p>
            </div>
        @endif

        @if ($studentData['inProgress']->isNotEmpty())
            <div class="card border-brand-300 bg-brand-50/50 dark:border-brand-500/40 dark:bg-brand-500/10">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Bài đang làm dở</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($studentData['inProgress'] as $exam)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $exam->title }}</p>
                                <p class="text-xs text-slate-400">{{ $exam->type->label() }} · {{ $exam->exam_questions_count }} câu</p>
                            </div>
                            <a href="{{ route('student.take', $exam) }}" wire:navigate class="btn btn-primary px-3 py-1.5 text-xs">Tiếp tục làm</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Bài sắp tới</h2>
            <div class="mt-4 space-y-2">
                @forelse ($studentData['available'] as $exam)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10" wire:key="avail-{{ $exam->id }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $exam->title }}</p>
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $exam->type->label() }}</span>
                            </div>
                            <p class="text-xs text-slate-400">
                                {{ $exam->exam_questions_count }} câu · {{ (float) $exam->total_points }} điểm
                                @if ($exam->duration_minutes) · {{ $exam->duration_minutes }} phút @endif
                                @if ($exam->due_at) · hạn {{ $exam->due_at->format('d/m/Y H:i') }} @endif
                            </p>
                        </div>
                        <a href="{{ route('student.take', $exam) }}" wire:navigate class="btn btn-primary px-3 py-1.5 text-xs">Làm bài</a>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Hiện không có bài nào cần làm.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Kết quả gần đây</h2>
            <div class="mt-4 space-y-2">
                @forelse ($studentData['recent'] as $attempt)
                    <a href="{{ route('student.result', $attempt->exam) }}" wire:navigate
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-300 dark:border-white/10">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $attempt->exam?->title }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $attempt->status->label() }}
                                @if ($attempt->submitted_at) · {{ $attempt->submitted_at->format('d/m/Y H:i') }} @endif
                            </p>
                        </div>
                        <span class="font-bold text-brand-600 dark:text-brand-400">{{ (float) $attempt->score }} điểm</span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Chưa có kết quả nào.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Giáo viên --}}
    @if ($teacherData)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Học sinh trong đội</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $teacherData['members'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Bài chờ chấm</p>
                <p class="mt-1 text-3xl font-bold {{ $teacherData['pendingGrading'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $teacherData['pendingGrading'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Đang giao</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $teacherData['published'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <a href="{{ route('studio') }}" wire:navigate class="card transition hover:border-brand-300 hover:shadow-md">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon name="sparkles" class="h-6 w-6" />
                </span>
                <h2 class="mt-3 font-semibold text-slate-900 dark:text-white">Studio</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Soạn đề, bài tập và tài liệu cho môn của bạn.</p>
            </a>
            <a href="{{ route('students') }}" wire:navigate class="card transition hover:border-brand-300 hover:shadow-md">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon name="users" class="h-6 w-6" />
                </span>
                <h2 class="mt-3 font-semibold text-slate-900 dark:text-white">Quản lý học sinh</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Thêm hoặc gỡ học sinh khỏi đội tuyển.</p>
            </a>
        </div>
    @endif

    {{-- Quản trị viên --}}
    @if ($roleCounts)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Giáo viên</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $roleCounts['teacher'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Học sinh</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $roleCounts['student'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500 dark:text-slate-400">Môn học</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ $roleCounts['subject'] }}</p>
            </div>
        </div>

        <div class="card">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lộ trình xây dựng</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @php
                    $phases = [
                        ['P0', 'Nền tảng: xác thực, phân quyền, tách biệt môn, PWA', true],
                        ['P1', 'Quản trị: người dùng, phân môn, API key', true],
                        ['P2', 'Studio giáo viên: ngân hàng câu hỏi, đề thi, bài tập, tài liệu', true],
                        ['P3', 'Học sinh: làm bài, chấm điểm, thông tin, xếp hạng, hồ sơ', true],
                        ['P4', 'Sơ đồ kiến thức (bảng trắng + xuất ảnh/PDF/JSON)', true],
                        ['P5', 'AI, thông báo và Web Push, thống kê', true],
                        ['P6', 'Tối ưu hiệu năng và triển khai Hostinger', false],
                    ];
                @endphp
                @foreach ($phases as [$code, $label, $done])
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold
                            {{ $done ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400' }}">
                            {{ $done ? '✓' : substr($code, 1) }}
                        </span>
                        <div>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $code }}</span>
                            <span class="text-slate-600 dark:text-slate-300"> — {{ $label }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
