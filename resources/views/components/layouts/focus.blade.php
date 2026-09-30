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
    <meta id="theme-color-meta" name="theme-color" content="{{ config('awawa.brand.primary') }}">
    <meta name="vapid-public-key" content="{{ config('awawa.webpush.public_key') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">

    <title>{{ $title ? $title.' · '.$brand : $brand }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <x-theme-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full">
    <div x-data="{
        show: false,
        width: 0,
        timer: null,
        start() {
            this.show = true;
            this.width = 8;
            clearInterval(this.timer);
            this.timer = setInterval(() => { this.width = Math.min(90, this.width + (90 - this.width) * 0.12); }, 120);
        },
        done() {
            clearInterval(this.timer);
            this.width = 100;
            setTimeout(() => { this.show = false; this.width = 0; }, 200);
        },
    }" x-on:livewire:navigating.window="start()" x-on:livewire:navigated.window="done()"
        x-show="show" x-cloak class="fixed inset-x-0 top-0 z-[100] h-[3px]">
        <div class="h-full bg-brand-600 transition-[width] duration-150 dark:bg-brand-400" :style="`width: ${width}%`"></div>
    </div>
    <header class="sticky top-0 z-30 border-b border-rule bg-white/95 backdrop-blur dark:border-night-700 dark:bg-night-800/95">
        <div class="mx-auto flex h-14 w-full max-w-4xl items-center justify-between px-4">
            <x-logo class="h-8 w-8" text-class="text-base" />
            <a href="{{ route('dashboard') }}" wire:navigate.hover class="btn btn-ghost px-3 py-2 text-xs">Thoát ra</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-4xl px-4 py-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
