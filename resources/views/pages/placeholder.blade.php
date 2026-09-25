@props(['title' => 'Sắp ra mắt', 'phase' => null, 'description' => null])

<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
        @if ($phase)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sẽ hoàn thiện ở giai đoạn <span class="font-semibold text-brand-600 dark:text-brand-400">{{ $phase }}</span></p>
        @endif
    </header>

    <div class="card flex flex-col items-center justify-center py-14 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
            <x-icon name="bolt" class="h-7 w-7" />
        </span>
        <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Tính năng đang được xây dựng</h2>
        <p class="mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
            {{ $description ?? 'Khu vực này đã được định hình trong kiến trúc và sẽ có đầy đủ chức năng ở giai đoạn tiếp theo của lộ trình.' }}
        </p>
        <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-outline mt-6">Về trang chủ</a>
    </div>
</div>
