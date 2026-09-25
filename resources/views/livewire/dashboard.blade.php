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

    @if ($user->isStudent() && ! $user->isActiveMemberOf($user->subject_id))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            <p class="font-semibold">Bạn chưa thuộc đội tuyển môn nào</p>
            <p class="mt-1">Hiện bạn chỉ có thể xem tài liệu công khai. Hãy liên hệ giáo viên bộ môn để được thêm vào đội tuyển và bắt đầu làm bài, thi và tính điểm.</p>
        </div>
    @endif

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
    @endif

    <div class="card">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lộ trình xây dựng</h2>
        <ul class="mt-4 space-y-3 text-sm">
            @php
                $phases = [
                    ['P0', 'Nền tảng: xác thực, phân quyền, tách biệt môn, PWA', true],
                    ['P1', 'Quản trị: người dùng, phân môn, API key', true],
                    ['P2', 'Studio giáo viên: ngân hàng câu hỏi, đề thi, bài tập, tài liệu', true],
                    ['P3', 'Học sinh: làm bài, chấm điểm, thông tin, xếp hạng, hồ sơ', false],
                    ['P4', 'Sơ đồ kiến thức (bảng trắng + xuất ảnh/PDF/JSON)', false],
                    ['P5', 'AI, thông báo và Web Push, thống kê', false],
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
</div>
