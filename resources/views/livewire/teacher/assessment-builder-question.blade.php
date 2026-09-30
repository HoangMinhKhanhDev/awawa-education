<div class="flex items-start gap-3 px-5 py-3.5" wire:key="eq-{{ $examQuestion->id }}">
    <span class="tnum mt-0.5 w-5 shrink-0 text-sm font-semibold text-ink-faint dark:text-slate-500">{{ $questionNo }}</span>
    <div class="min-w-0 flex-1">
        @if ($examQuestion->question?->type === \App\Enums\QuestionType::TrueFalseCluster)
            @if (filled($examQuestion->question->content))
                <div class="notebook-markdown text-sm leading-relaxed text-ink dark:text-slate-200">{!! \Illuminate\Support\Str::markdown($examQuestion->question->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
            @endif
            <ul class="mt-1.5 space-y-0.5">
                @foreach ($examQuestion->question->options as $subIndex => $sub)
                    <li class="flex items-center gap-2 text-sm {{ $sub->is_correct ? 'font-medium text-success' : 'text-ink-soft dark:text-slate-400' }}">
                        <span class="tnum w-5 shrink-0 font-semibold text-ink-faint dark:text-slate-500">{{ chr(97 + $subIndex) }})</span>
                        <span class="min-w-0 flex-1">{{ $sub->content }}</span>
                        <span class="shrink-0 text-xs {{ $sub->is_correct ? 'text-success' : 'text-ink-faint dark:text-slate-500' }}">{{ $sub->is_correct ? 'Đúng' : 'Sai' }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="line-clamp-2 text-sm leading-relaxed text-ink dark:text-slate-200">{{ $examQuestion->question?->content }}</p>
        @endif
        <p class="mt-1 flex flex-wrap items-center gap-x-2.5 text-xs text-ink-faint dark:text-slate-500">
            <span>{{ $examQuestion->question?->type->label() }}</span>
            <span>{{ $examQuestion->question?->difficulty?->label() }}</span>
        </p>
    </div>
    <div class="flex shrink-0 items-center gap-1">
        <input type="number" step="0.25" min="0" class="input w-16 px-2 py-1 text-xs tnum"
            value="{{ (float) ($examQuestion->points ?? $examQuestion->question?->points) }}"
            wire:change="setQuestionPoints({{ $examQuestion->id }}, $event.target.value)" title="Điểm">
        @if ($examQuestion->question?->type === \App\Enums\QuestionType::TrueFalseCluster)
            <button type="button" wire:click="editCluster({{ $examQuestion->id }})"
                class="rounded-[10px] border border-rule px-2 py-1 text-xs text-ink-soft hover:bg-paper-2 dark:border-night-700 dark:text-slate-400 dark:hover:bg-white/5" title="Sửa cụm Đúng/Sai">Sửa</button>
        @endif
        <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'up')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Lên">↑</button>
        <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'down')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Xuống">↓</button>
        <button type="button" wire:click="removeQuestion({{ $examQuestion->id }})" class="rounded-[10px] px-1.5 py-1 text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" title="Gỡ">✕</button>
    </div>
</div>
