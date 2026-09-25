@props(['active' => 'users'])

@php
    $tabs = [
        ['key' => 'users', 'route' => 'admin.users', 'label' => 'Người dùng', 'icon' => 'users'],
        ['key' => 'subjects', 'route' => 'admin.subjects', 'label' => 'Môn học', 'icon' => 'cap'],
        ['key' => 'api-keys', 'route' => 'admin.api-keys', 'label' => 'API key', 'icon' => 'key'],
        ['key' => 'stats', 'route' => 'admin.stats', 'label' => 'Thống kê', 'icon' => 'chart'],
    ];
@endphp

<nav class="tabs" aria-label="Khu vực quản trị">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}" wire:navigate class="tab {{ $active === $tab['key'] ? 'tab-active' : '' }}">
            <x-icon :name="$tab['icon']" class="h-4 w-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
