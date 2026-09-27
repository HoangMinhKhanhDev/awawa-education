@php
    $brand = config('awawa.brand.name');
@endphp

<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta id="theme-color-meta" name="theme-color" content="{{ config('awawa.brand.primary') }}">
    <title>{{ $brand }} — Ôn thi cùng đội tuyển</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <x-theme-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="paper-grid min-h-full">
    <header class="mx-auto flex w-full max-w-3xl items-center justify-between px-4 py-4 sm:px-6 lg:py-6">
        <x-logo class="h-10 w-10" />
        <div class="flex items-center gap-1.5">
            <button type="button" data-theme-toggle onclick="window.awawa.cyclePreference()"
                class="flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                aria-label="Đổi giao diện">
                <x-icon name="sun" class="theme-icon theme-icon-light h-5 w-5" />
                <x-icon name="moon" class="theme-icon theme-icon-dark h-5 w-5" />
                <x-icon name="monitor" class="theme-icon theme-icon-system h-5 w-5" />
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Vào ứng dụng</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl px-4 pb-20 sm:px-6">
        <section class="flex flex-col items-center py-14 text-center sm:py-20">
            <p class="text-[13px] font-medium uppercase tracking-[0.14em] text-ink-faint dark:text-slate-500">
                Đội tuyển học sinh giỏi
            </p>

            <h1 class="mt-4 max-w-[16ch] font-serif text-[36px] font-semibold leading-[1.1] tracking-[-0.015em] text-ink sm:text-[48px] dark:text-white">
                Ôn thi cùng đội. Điểm số chờ bạn.
            </h1>

            <p class="mt-5 max-w-[46ch] text-[15px] leading-relaxed text-ink-soft dark:text-slate-400">
                Đăng nhập để làm bài, xem điểm và nhận đề mới từ giáo viên. Mỗi môn một góc riêng, không lẫn vào nhau.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary px-5 py-3">Tạo tài khoản học sinh</a>
                <a href="{{ route('login') }}" class="btn btn-outline px-5 py-3">Đăng nhập</a>
            </div>

            <p class="mt-5 max-w-[44ch] text-[13px] leading-relaxed text-ink-faint dark:text-slate-500">
                Tự đăng ký trong vài giây. Giáo viên sẽ thêm bạn vào đội tuyển để bắt đầu làm bài.
            </p>
        </section>
    </main>

    <footer class="border-t border-rule py-8 dark:border-night-700">
        <p class="mx-auto max-w-3xl px-4 text-xs text-ink-faint sm:px-6 dark:text-slate-500">
            &copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi
        </p>
    </footer>
</body>
</html>
