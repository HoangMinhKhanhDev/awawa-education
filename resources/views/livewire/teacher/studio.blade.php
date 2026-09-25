@php
    $enabledFeatures = $subject?->enabledFeatureKeys() ?? [];
@endphp

<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Studio</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            @if ($subject)
                Không gian làm việc cho môn
                <span class="font-semibold" style="color: {{ $subject->color }}">{{ $subject->name }}</span>
                · {{ $memberCount }} học sinh trong đội.
            @else
                Bạn chưa được phân môn. Hãy liên hệ quản trị viên.
            @endif
        </p>
    </header>

    @if (! $subject)
        <div class="card text-center text-sm text-slate-500 dark:text-slate-400">
            Studio chỉ hoạt động khi tài khoản giáo viên được phân một môn.
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($sections as $section)
                @php $enabled = in_array($section['feature']->value, $enabledFeatures, true); @endphp

                @if ($enabled)
                    <a href="{{ route($section['route']) }}" wire:navigate class="card transition hover:border-brand-300 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                <x-icon :name="$section['icon']" class="h-6 w-6" />
                            </span>
                            <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $section['count']() }}</span>
                        </div>
                        <h2 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $section['feature']->label() }}</h2>
                        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ $section['description'] }}</p>
                    </a>
                @else
                    <div class="card opacity-60">
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5">
                                <x-icon :name="$section['icon']" class="h-6 w-6" />
                            </span>
                            <span class="badge bg-slate-100 text-slate-400 dark:bg-white/5">Đang tắt</span>
                        </div>
                        <h2 class="mt-4 font-semibold text-slate-500 dark:text-slate-400">{{ $section['feature']->label() }}</h2>
                        <p class="mt-1.5 text-sm text-slate-400">Tính năng chưa được bật cho môn này.</p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
