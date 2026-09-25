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
<body class="min-h-full">
    <div class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-10">
        <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-brand-800/20 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <div class="mb-6 flex flex-col items-center text-center">
                <x-logo class="h-14 w-14" :show-text="false" />
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ $title ?? 'Chào mừng tới awawa' }}
                </h1>
                @if ($subtitle)
                    <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>

            <div class="card p-6">
                {{ $slot }}
            </div>
        </div>

        <p class="relative mt-6 text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi
        </p>
    </div>

    @livewireScripts
</body>
</html>
