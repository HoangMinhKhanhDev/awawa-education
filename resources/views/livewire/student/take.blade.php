@php
    $answered = collect($answers)->filter(fn ($a) => ! empty($a['selected']) || filled($a['text'] ?? null))->count();
    $total = $examQuestions->count();
@endphp

<div class="space-y-6" wire:poll.30s="saveProgress"
    x-data="{
        remaining: 0,
        timer: null,
        init() {
            const iso = @js($expiresAtIso);
            if (! iso) { return; }
            const end = new Date(iso).getTime();
            const tick = () => {
                this.remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
                if (this.remaining <= 0) { clearInterval(this.timer); $wire.submit(); }
            };
            tick();
            this.timer = setInterval(tick, 1000);
        },
        fmt(sec) {
            const h = Math.floor(sec / 3600);
            const m = Math.floor((sec % 3600) / 60);
            const s = sec % 60;
            return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
        },
    }">
    <header class="sticky top-16 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:top-0 dark:border-white/10 dark:bg-night-900/95">
        <div class="min-w-0">
            <h1 class="truncate text-lg font-bold text-slate-900 dark:text-white">{{ $exam->title }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $answered }}/{{ $total }} câu đã trả lời</p>
        </div>

        <div class="flex items-center gap-2">
            @if ($violations > 0)
                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" title="Số lần rời màn hình làm bài">
                    Cảnh báo: {{ $violations }}
                </span>
            @endif

            @if ($expiresAtIso)
                <span class="rounded-xl bg-slate-100 px-3 py-1.5 font-mono text-sm font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200"
                    :class="remaining <= 60 && '!bg-red-100 !text-red-700 dark:!bg-red-500/20 dark:!text-red-300'">
                    <span x-text="fmt(remaining)">--:--:--</span>
                </span>
            @endif

            <button type="button" wire:click="submit" wire:confirm="Nộp bài? Bạn không thể sửa sau khi nộp."
                class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Nộp bài</span>
                <span wire:loading wire:target="submit">Đang nộp…</span>
            </button>
        </div>
    </header>

    @if ($exam->shuffle_questions || $exam->shuffle_options)
        <p class="text-xs text-slate-400">Đề được trộn thứ tự {{ $exam->shuffle_questions ? 'câu hỏi' : '' }}{{ $exam->shuffle_questions && $exam->shuffle_options ? ' và ' : '' }}{{ $exam->shuffle_options ? 'đáp án' : '' }} riêng cho bạn.</p>
    @endif

    <div class="space-y-4">
        @foreach ($examQuestions as $index => $examQuestion)
            @php $question = $examQuestion->question; @endphp
            <div class="card" wire:key="take-q-{{ $examQuestion->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">{{ $index + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span>{{ $question->type->label() }}</span>
                            <span>· {{ (float) ($examQuestion->points ?? $question->points) }} điểm</span>
                        </div>
                        <p class="mt-1.5 whitespace-pre-line text-slate-800 dark:text-slate-100">{{ $question->content }}</p>

                        @if ($question->type === \App\Enums\QuestionType::MultipleChoice)
                            <div class="mt-3 space-y-2">
                                @foreach ($optionOrder($question->options) as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm transition hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                                        wire:key="opt-{{ $option->id }}">
                                        <input type="radio" name="q-{{ $question->id }}" value="{{ $option->id }}"
                                            wire:model="answers.{{ $question->id }}.selected"
                                            class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                                        <span class="text-slate-700 dark:text-slate-200">{{ $option->content }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($question->type === \App\Enums\QuestionType::FillBlank)
                            <input type="text" class="input mt-3" wire:model="answers.{{ $question->id }}.text" placeholder="Nhập đáp án ngắn">
                        @else
                            <textarea rows="4" class="input mt-3" wire:model="answers.{{ $question->id }}.text" placeholder="Trình bày lời giải..."></textarea>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <button type="button" wire:click="submit" wire:confirm="Nộp bài? Bạn không thể sửa sau khi nộp."
            class="btn btn-primary px-6 py-3" wire:loading.attr="disabled">
            Nộp bài
        </button>
    </div>

    @script
    <script>
        window.__awawaExamNotify = (type) => $wire.logAntiCheat(type);

        if (! window.__awawaExamHooks) {
            window.__awawaExamHooks = true;

            const notify = (type) => {
                if (window.__awawaExamNotify) window.__awawaExamNotify(type);
            };

            document.addEventListener('visibilitychange', () => { if (document.hidden) notify('tab_hidden'); });
            window.addEventListener('blur', () => notify('window_blur'));
            document.addEventListener('fullscreenchange', () => { if (! document.fullscreenElement) notify('fullscreen_exit'); });
            document.addEventListener('copy', (event) => event.preventDefault());
            document.addEventListener('contextmenu', (event) => event.preventDefault());
        }
    </script>
    @endscript
</div>
