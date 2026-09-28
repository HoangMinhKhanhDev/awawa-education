@props(['items' => []])

<ul class="space-y-1">
    @foreach ($items as $item)
        <li>
            <div class="flex items-start gap-2 rounded-[10px] px-2.5 py-1.5 {{ ($item['depth'] ?? 1) === 0 ? 'bg-brand-50 font-semibold text-ink dark:bg-brand-500/10 dark:text-white' : 'text-ink-soft dark:text-slate-300' }}">
                <span class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full {{ ($item['depth'] ?? 1) === 0 ? 'bg-brand-600 dark:bg-brand-400' : (($item['depth'] ?? 1) === 1 ? 'bg-sky-500' : 'bg-rule-strong dark:bg-slate-600') }}"></span>
                <span class="text-sm leading-relaxed">{{ $item['node']['label'] ?? '' }}</span>
            </div>
            @if (! empty($item['children']))
                <div class="ml-[15px] border-l border-rule pl-3 dark:border-night-700">
                    <x-mindmap-tree :items="$item['children']" />
                </div>
            @endif
        </li>
    @endforeach
</ul>
