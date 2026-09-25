@props(['title' => null, 'subtitle' => null])

@php
    $brand = config('awawa.brand.name');
@endphp

<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('awawa.brand.primary') }}">
    <meta name="vapid-public-key" content="{{ config('awawa.webpush.public_key') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">

    <title>{{ $title ? $title.' · '.$brand : $brand }}</title>

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
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (error) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="paper-grid min-h-full">
    <div class="min-h-dvh lg:grid lg:grid-cols-[1.05fr_1fr]">
        {{-- Bìa giới thiệu (desktop) --}}
        <aside class="hidden flex-col justify-between border-r border-rule bg-white/75 p-10 backdrop-blur lg:flex dark:border-night-700 dark:bg-night-800/70">
            <x-logo class="h-10 w-10" />

            <div class="max-w-md">
                <p class="font-serif text-[32px] font-semibold leading-[1.15] tracking-[-0.01em] text-ink dark:text-white">
                    Một chỗ cho cả đội tuyển: đề, bài, điểm và sơ đồ kiến thức.
                </p>
                <p class="mt-4 text-[15px] leading-relaxed text-ink-soft dark:text-slate-400">
                    Giáo viên từng môn soạn đề và giao bài; học sinh làm bài, xem điểm và dựng sơ đồ. Dữ liệu tách riêng theo môn.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-ink-soft dark:text-slate-400">
                    <li class="flex gap-3"><span class="mt-2 h-px w-6 shrink-0 bg-rule-strong dark:bg-night-700"></span>Ngân hàng câu hỏi, đề thi, bài tập và tài liệu.</li>
                    <li class="flex gap-3"><span class="mt-2 h-px w-6 shrink-0 bg-rule-strong dark:bg-night-700"></span>Chấm trắc nghiệm tự động, chấm tự luận có nhận xét.</li>
                    <li class="flex gap-3"><span class="mt-2 h-px w-6 shrink-0 bg-rule-strong dark:bg-night-700"></span>Cài như ứng dụng, chạy nhẹ trên điện thoại yếu.</li>
                </ul>
            </div>

            <p class="text-xs text-ink-faint dark:text-slate-500">&copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi</p>
        </aside>

        {{-- Form --}}
        <main class="flex min-h-dvh items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md">
                <div class="mb-6 flex flex-col items-start">
                    <x-logo class="h-11 w-11" text-class="text-lg lg:hidden" />
                    <h1 class="page-title mt-4">{{ $title ?? 'Chào mừng tới awawa' }}</h1>
                    @if ($subtitle)
                        <p class="page-sub">{{ $subtitle }}</p>
                    @endif
                </div>

                <div class="panel panel-pad">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-xs text-ink-faint lg:hidden dark:text-slate-500">
                    &copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi
                </p>
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
