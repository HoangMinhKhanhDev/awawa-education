@props(['active' => 'questions'])

@php
    $subject = app(\App\Support\SubjectContext::class)->subject();
    $enabled = $subject?->enabledFeatureKeys() ?? [];

    $tabs = [
        ['key' => 'questions', 'route' => 'studio.questions', 'label' => 'Câu hỏi', 'feature' => 'question_bank', 'icon' => 'sparkles'],
        ['key' => 'exams', 'route' => 'studio.exams', 'label' => 'Đề thi', 'feature' => 'exams', 'icon' => 'cap'],
        ['key' => 'assignments', 'route' => 'studio.assignments', 'label' => 'Bài tập', 'feature' => 'assignments', 'icon' => 'check'],
        ['key' => 'documents', 'route' => 'studio.documents', 'label' => 'Tài liệu', 'feature' => 'documents', 'icon' => 'mail'],
        ['key' => 'announcements', 'route' => 'studio.announcements', 'label' => 'Thông báo', 'feature' => 'announcements', 'icon' => 'bell'],
        ['key' => 'ai', 'route' => 'studio.ai', 'label' => 'AI', 'feature' => 'ai_tools', 'icon' => 'sparkles'],
    ];
@endphp

<nav class="tabs" aria-label="Studio">
    <a href="{{ route('studio') }}" wire:navigate class="tab {{ $active === 'studio' ? 'tab-active' : '' }}">
        <x-icon name="home" class="h-4 w-4" />
        Studio
    </a>
    @foreach ($tabs as $tab)
        @continue (! in_array($tab['feature'], $enabled, true))
        <a href="{{ route($tab['route']) }}" wire:navigate class="tab {{ $active === $tab['key'] ? 'tab-active' : '' }}">
            <x-icon :name="$tab['icon']" class="h-4 w-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
