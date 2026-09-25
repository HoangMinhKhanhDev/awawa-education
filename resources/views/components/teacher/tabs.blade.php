@props(['active' => 'questions'])

@php
    $subject = app(\App\Support\SubjectContext::class)->subject();
    $enabled = $subject?->enabledFeatureKeys() ?? [];

    $tabs = [
        ['key' => 'questions', 'route' => 'studio.questions', 'label' => 'Câu hỏi', 'feature' => 'question_bank', 'icon' => 'sparkles'],
        ['key' => 'exams', 'route' => 'studio.exams', 'label' => 'Đề thi', 'feature' => 'exams', 'icon' => 'cap'],
        ['key' => 'assignments', 'route' => 'studio.assignments', 'label' => 'Bài tập', 'feature' => 'assignments', 'icon' => 'check'],
        ['key' => 'documents', 'route' => 'studio.documents', 'label' => 'Tài liệu', 'feature' => 'documents', 'icon' => 'mail'],
    ];
@endphp

<nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
    aria-label="Studio">
    <a href="{{ route('studio') }}" wire:navigate
        class="flex flex-none items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active === 'studio'
            ? 'bg-brand-600 text-white'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' }}">
        <x-icon name="home" class="h-4 w-4" />
        Studio
    </a>
    @foreach ($tabs as $tab)
        @continue (! in_array($tab['feature'], $enabled, true))
        <a href="{{ route($tab['route']) }}" wire:navigate
            class="flex flex-none items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active === $tab['key']
                ? 'bg-brand-600 text-white'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' }}">
            <x-icon :name="$tab['icon']" class="h-4 w-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
