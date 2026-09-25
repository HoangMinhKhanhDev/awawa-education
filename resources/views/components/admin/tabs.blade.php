@props(['active' => 'users'])

@php
    $tabs = [
        ['key' => 'users', 'route' => 'admin.users', 'label' => 'Người dùng', 'icon' => 'users'],
        ['key' => 'subjects', 'route' => 'admin.subjects', 'label' => 'Môn học', 'icon' => 'cap'],
        ['key' => 'api-keys', 'route' => 'admin.api-keys', 'label' => 'API key', 'icon' => 'key'],
        ['key' => 'stats', 'route' => 'admin.stats', 'label' => 'Thống kê', 'icon' => 'chart'],
    ];
@endphp

<nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
    aria-label="Khu vực quản trị">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}" wire:navigate
            class="flex flex-none items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active === $tab['key']
                ? 'bg-brand-600 text-white'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' }}">
            <x-icon :name="$tab['icon']" class="h-4 w-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
