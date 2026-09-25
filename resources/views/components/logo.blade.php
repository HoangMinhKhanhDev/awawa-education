@props(['class' => 'h-9 w-9', 'showText' => true, 'textClass' => 'text-ink dark:text-white'])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <svg class="{{ $class }} shrink-0" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg" role="img"
        aria-label="awawa HSG">
        <defs>
            <linearGradient id="awawaLogoGradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#3B82F6" />
                <stop offset="1" stop-color="#1E40AF" />
            </linearGradient>
        </defs>
        <rect x="6" y="6" width="244" height="244" rx="60" fill="url(#awawaLogoGradient)" />
        <text x="128" y="132" text-anchor="middle" dominant-baseline="middle"
            font-family="'Be Vietnam Pro', 'Segoe UI', Arial, sans-serif" font-size="92" font-weight="700"
            fill="#ffffff" letter-spacing="2">HSG</text>
    </svg>

    @if ($showText)
        <span class="font-serif text-[19px] font-semibold leading-none tracking-[-0.01em] {{ $textClass }}">awawa</span>
    @endif
</div>
