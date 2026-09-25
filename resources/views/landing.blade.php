@php
    $brand = config('awawa.brand.name');
@endphp

<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('awawa.brand.primary') }}">
    <title>{{ $brand }} — Nền tảng đội tuyển học sinh giỏi</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('awawa-theme');
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                if (theme === 'dark') document.documentElement.classList.add('dark');
            } catch (error) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute -top-32 right-0 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute top-40 -left-24 h-96 w-96 rounded-full bg-brand-800/20 blur-3xl"></div>

        <header class="relative mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5">
            <x-logo class="h-10 w-10" />
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.awawa.toggleTheme()"
                    class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5"
                    aria-label="Đổi chế độ sáng/tối">
                    <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
                    <x-icon name="moon" class="h-5 w-5 dark:hidden" />
                </button>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Vào ứng dụng</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Bắt đầu</a>
                @endauth
            </div>
        </header>

        <main class="relative mx-auto w-full max-w-6xl px-4 pb-20 pt-10">
            <div class="mx-auto max-w-3xl text-center">
                <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Dành cho đội tuyển học sinh giỏi</span>
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                    Quản lý đội tuyển <span class="bg-gradient-to-r from-brand-500 to-brand-800 bg-clip-text text-transparent">gọn gàng và nhẹ nhàng</span>
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base text-slate-600 dark:text-slate-300">
                    {{ $brand }} giúp giáo viên từng môn tạo đề, tài liệu và bài tập; học sinh làm bài, theo dõi điểm và xây dựng sơ đồ kiến thức — tất cả trong một PWA nhẹ, chạy mượt trên cả điện thoại yếu.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register') }}" class="btn btn-primary px-6 py-3">
                        Tạo tài khoản học sinh
                        <x-icon name="arrow-right" class="h-5 w-5" />
                    </a>
                    <a href="{{ route('login') }}" class="btn btn-outline px-6 py-3">Đăng nhập</a>
                </div>
            </div>

            <div class="mt-16 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['sparkles', 'Studio theo môn', 'Mỗi môn có bộ tính năng riêng, tách biệt hoàn toàn, không lẫn dữ liệu.'],
                    ['map', 'Sơ đồ kiến thức', 'Bảng trắng tự do tạo node và liên kết, lưu trạng thái và xuất ảnh/PDF/JSON.'],
                    ['chart', 'Điểm số & xếp hạng', 'Chấm trắc nghiệm tự động, tổng hợp điểm và bảng xếp hạng theo môn.'],
                    ['bell', 'Thông báo', 'Nhắc hạn nộp, đề mới và kết quả qua thông báo trong app và Web Push.'],
                    ['key', 'API & AI', 'Quản lý API key, tích hợp AI tạo đề qua OpenRouter và Agnes AI.'],
                    ['bolt', 'PWA tải nhanh', 'Cài như ứng dụng, hoạt động nhẹ trên thiết bị cấu hình thấp.'],
                ] as [$icon, $title, $desc])
                    <div class="card">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <x-icon :name="$icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </main>

        <footer class="relative border-t border-slate-200 py-8 text-center text-xs text-slate-400 dark:border-white/10">
            &copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi
        </footer>
    </div>
</body>
</html>
