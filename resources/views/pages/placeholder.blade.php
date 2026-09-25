@props(['title' => 'Sắp ra mắt', 'phase' => null, 'description' => null])

<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            @if ($phase)
                <p class="page-sub">Hoàn thiện ở giai đoạn {{ $phase }} của lộ trình.</p>
            @endif
        </div>
    </div>

    <div class="panel panel-pad">
        <p class="text-sm leading-relaxed text-ink-soft dark:text-slate-400">
            {{ $description ?? 'Khu vực này đã có trong kiến trúc và sẽ đầy đủ chức năng ở giai đoạn tiếp theo.' }}
        </p>
        <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-outline mt-5">Về trang chủ</a>
    </div>
</div>
