@php
    $enabledFeatures = $subject?->enabledFeatureKeys() ?? [];
@endphp

<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Studio</h1>
            <p class="page-sub">
                @if ($subject)
                    Không gian làm việc cho môn <span class="font-medium" style="color: {{ $subject->color }}">{{ $subject->name }}</span>
                    <span class="mx-1.5 text-rule-strong dark:text-night-700">—</span>
                    <span class="tnum">{{ $memberCount }} học sinh trong đội</span>
                @else
                    Bạn chưa được phân môn.
                @endif
            </p>
        </div>
    </div>

    @if (! $subject)
        <div class="panel">
            <p class="empty">Studio hoạt động khi tài khoản giáo viên được phân một môn. Liên hệ quản trị viên để được phân môn.</p>
        </div>
    @else
        <div class="panel">
            <div class="divide-y divide-rule dark:divide-night-700">
                @foreach ($sections as $section)
                    @php $enabled = in_array($section['feature']->value, $enabledFeatures, true); @endphp

                    @if ($enabled)
                        <a href="{{ route($section['route']) }}" wire:navigate
                            class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                            <x-icon :name="$section['icon']" class="h-5 w-5 shrink-0 text-brand-600 dark:text-brand-400" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink dark:text-slate-100">{{ $section['feature']->label() }}</p>
                                <p class="mt-0.5 text-sm text-ink-soft dark:text-slate-400">{{ $section['description'] }}</p>
                            </div>
                            <span class="tnum shrink-0 text-lg font-semibold text-ink-faint dark:text-slate-500">{{ $section['count']() }}</span>
                        </a>
                    @else
                        <div class="flex items-center gap-4 px-5 py-4 opacity-60">
                            <x-icon :name="$section['icon']" class="h-5 w-5 shrink-0 text-ink-faint dark:text-slate-600" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink-soft dark:text-slate-400">{{ $section['feature']->label() }}</p>
                                <p class="mt-0.5 text-sm text-ink-faint dark:text-slate-500">Tính năng chưa được bật cho môn này.</p>
                            </div>
                            <span class="chip chip-neutral shrink-0">Đang tắt</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
