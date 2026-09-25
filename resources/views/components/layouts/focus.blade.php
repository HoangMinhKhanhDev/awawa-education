@props(['title' => null])

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
<body class="min-h-full">
    <header class="sticky top-0 z-30 border-b border-rule bg-white/95 backdrop-blur dark:border-night-700 dark:bg-night-800/95">
        <div class="mx-auto flex h-14 w-full max-w-4xl items-center justify-between px-4">
            <x-logo class="h-8 w-8" text-class="text-base" />
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost px-3 py-2 text-xs">Thoát ra</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-4xl px-4 py-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
