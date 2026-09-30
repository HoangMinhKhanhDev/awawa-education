@props(['title' => null, 'fullBleed' => false])

@php
    $user = auth()->user();
    $navItems = $user ? \App\Support\Navigation::for($user) : [];
    $primaryItems = \App\Support\Navigation::primary($navItems);
    $subject = $user && ! $user->isSuperAdmin() && $user->subject_id ? $user->subject : null;
    $brand = config('awawa.brand.name');
    $initial = $user ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) : null;
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
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $brand }}">

    <title>{{ $title ? $title.' · '.$brand : $brand.' — Đội tuyển học sinh giỏi' }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <x-theme-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full">
    {{-- Thanh tiến độ chuyển trang: wire:navigate không có indicator mặc định nên
        click xong im lặng 1-3s mới đổi trang. Prefetch (hover) + thanh này làm
        cảm giác chuyển trang tức thì. --}}
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
    <div class="{{ $fullBleed ? 'h-dvh overflow-hidden' : 'min-h-dvh' }} lg:flex" x-data="{ drawer: false }">
        {{-- Rail desktop --}}
        <aside class="hidden w-64 shrink-0 border-r border-rule bg-white lg:flex lg:flex-col dark:border-night-700 dark:bg-night-800">
            <div class="flex h-16 items-center justify-between pl-5 pr-3">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center">
                    <x-logo />
                </a>
                <livewire:notifications.bell />
            </div>

            <div class="px-5"><div class="rule"></div></div>

            @if ($subject)
                <div class="px-5 pt-4">
                    <div class="chip chip-neutral w-full justify-center" style="color: {{ $subject->color }}">
                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $subject->color }}"></span>
                        {{ $subject->name }}
                    </div>
                </div>
            @endif

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4" aria-label="Điều hướng chính">
                @foreach ($navItems as $item)
                    <a href="{{ $item['url'] }}" wire:navigate.hover class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
                        <x-icon :name="$item['icon']" class="h-[18px] w-[18px]" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-rule p-4 dark:border-night-700">
                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                        @if ($user?->avatar)
                            <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy" width="36" height="36" class="h-full w-full object-cover">
                        @else
                            {{ $initial }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink dark:text-slate-100">{{ $user?->name }}</p>
                        <p class="truncate text-xs text-ink-faint dark:text-slate-400">{{ $user?->role?->label() }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" data-theme-toggle onclick="window.awawa.cyclePreference()" class="btn btn-outline flex-1 px-3 py-2" aria-label="Đổi giao diện">
                        <x-icon name="sun" class="theme-icon theme-icon-light h-4 w-4" />
                        <x-icon name="moon" class="theme-icon theme-icon-dark h-4 w-4" />
                        <x-icon name="monitor" class="theme-icon theme-icon-system h-4 w-4" />
                    </button>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="btn btn-ghost w-full px-3 py-2">
                            <x-icon name="logout" class="h-4 w-4" />
                            <span class="text-xs">Thoát</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col {{ $fullBleed ? 'h-full min-h-0' : '' }}">
            {{-- Top bar mobile --}}
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-rule bg-white/95 px-3 backdrop-blur lg:hidden dark:border-night-700 dark:bg-night-800/95">
                <div class="flex items-center gap-2">
                    <button type="button" @click="drawer = true"
                        class="flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                        aria-label="Mở menu">
                        <x-icon name="menu" class="h-[22px] w-[22px]" />
                    </button>
                    <a href="{{ route('dashboard') }}" wire:navigate.hover
                        <x-logo class="h-8 w-8" text-class="text-base" />
                    </a>
                </div>

                <div class="flex items-center gap-0.5">
                    <livewire:notifications.bell />
                    <button type="button" data-theme-toggle onclick="window.awawa.cyclePreference()"
                        class="flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                        aria-label="Đổi giao diện">
                        <x-icon name="sun" class="theme-icon theme-icon-light h-5 w-5" />
                        <x-icon name="moon" class="theme-icon theme-icon-dark h-5 w-5" />
                        <x-icon name="monitor" class="theme-icon theme-icon-system h-5 w-5" />
                    </button>
                </div>
            </header>

            <main id="app-content" class="{{ $fullBleed ? 'flex min-h-0 flex-1 flex-col overflow-hidden' : 'flex-1' }}">
                @if ($fullBleed)
                    <div class="flex h-full min-h-0 w-full flex-col">{{ $slot }}</div>
                @else
                    <div class="mx-auto w-full max-w-5xl px-4 pb-28 pt-6 lg:px-8 lg:pb-14 lg:pt-8">{{ $slot }}</div>
                @endif
            </main>
        </div>

        {{-- Drawer mobile --}}
        <div x-cloak x-show="drawer" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-show="drawer" x-transition.opacity @click="drawer = false"
                class="absolute inset-0 bg-night-900/60"></div>

            <div x-show="drawer" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="absolute left-0 top-0 flex h-full w-72 max-w-[82%] flex-col border-r border-rule bg-white dark:border-night-700 dark:bg-night-800">
                <div class="flex h-16 items-center justify-between pl-5 pr-3">
                    <x-logo />
                    <button type="button" @click="drawer = false"
                        class="flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                        aria-label="Đóng menu">
                        <x-icon name="x" class="h-6 w-6" />
                    </button>
                </div>

                <div class="px-5"><div class="rule"></div></div>

                @if ($subject)
                    <div class="px-5 pt-4">
                        <div class="chip chip-neutral w-full justify-center" style="color: {{ $subject->color }}">
                            <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $subject->color }}"></span>
                            {{ $subject->name }}
                        </div>
                    </div>
                @endif

                <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
                    @foreach ($navItems as $item)
                        <a href="{{ $item['url'] }}" wire:navigate.hover class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
                            <x-icon :name="$item['icon']" class="h-[18px] w-[18px]" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-rule p-4 dark:border-night-700">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ $initial }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink dark:text-slate-100">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-ink-faint dark:text-slate-400">{{ $user?->role?->label() }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost w-full justify-start">
                            <x-icon name="logout" class="h-5 w-5" />
                            Đăng xuất
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Bottom nav mobile --}}
        @if ($primaryItems)
            <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-rule bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-night-700 dark:bg-night-800/95"
                aria-label="Điều hướng nhanh">
                <div class="mx-auto flex max-w-lg items-stretch justify-around">
                    @foreach ($primaryItems as $item)
                        <a href="{{ $item['url'] }}" wire:navigate.hover
                            class="relative flex flex-1 flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium {{ $item['active'] ? 'text-brand-700 dark:text-brand-300' : 'text-ink-faint dark:text-slate-400' }}">
                            @if ($item['active'])
                                <span class="absolute top-0 h-[2px] w-8 rounded-full bg-brand-600 dark:bg-brand-400"></span>
                            @endif
                            <x-icon :name="$item['icon']" class="h-[22px] w-[22px]" />
                            <span class="leading-none">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>

    @livewireScripts
</body>
</html>
