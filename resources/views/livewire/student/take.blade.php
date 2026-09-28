@php
    $answered = collect($answers)->filter(fn ($a) => ! empty($a['selected']) || filled($a['text'] ?? null))->count();
    $total = $examQuestions->count();
    $percent = $total > 0 ? (int) round($answered / $total * 100) : 0;
@endphp

<div class="space-y-6"
    x-data="{
        remaining: 0,
        timer: null,
        saveTimer: null,
        saving: false,
        init() {
            const iso = @js($expiresAtIso);
            const end = iso ? new Date(iso).getTime() : null;
            const tick = () => {
                if (end === null) { return; }
                this.remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
                if (this.remaining <= 0) {
                    clearInterval(this.timer);
                    $wire.submit();
                }
            };
            tick();
            this.timer = setInterval(tick, 1000);

            // Lưu tiến độ định kỳ, nhưng bỏ qua khi học sinh đang rời trang:
            // request lúc đó tốn pin và dữ liệu mà không đem lại gì, lại xếp
            // vào hàng đợi Livewire chen vào giữa các lần bấm.
            this.saveTimer = setInterval(() => {
                if (document.hidden || this.saving) { return; }
                this.saving = true;
                $wire.saveProgress().finally(() => { this.saving = false; });
            }, 45000);
        },
        fmt(sec) {
            const h = Math.floor(sec / 3600);
            const m = Math.floor((sec % 3600) / 60);
            const s = sec % 60;
            return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':');
        },
    }">
    <div class="panel sticky top-14 z-20">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5">
            <div class="min-w-0">
                <h1 class="exam-protected truncate text-lg font-semibold text-ink dark:text-white">{{ $exam->title }}</h1>
                <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">{{ $answered }}/{{ $total }} câu đã trả lời</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($violations > 0)
                    <span class="chip chip-warning" title="Số lần rời màn hình làm bài">{{ $violations }} cảnh báo</span>
                @endif

                @if ($expiresAtIso)
                    <span class="tnum rounded-[10px] border border-rule px-3 py-2 text-sm font-semibold text-ink dark:border-night-700 dark:text-slate-200"
                        :class="remaining <= 60 && 'border-transparent !bg-signal-soft !text-signal dark:!bg-signal/15 dark:!text-red-300'">
                        <span x-text="fmt(remaining)">--:--:--</span>
                    </span>
                @endif

                <button type="button" wire:click="submit" wire:confirm="Nộp bài? Bạn không thể sửa sau khi nộp."
                    class="btn btn-primary" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Nộp bài</span>
                    <span wire:loading wire:target="submit">Đang nộp…</span>
                </button>
            </div>
        </div>

        <div class="h-1 w-full bg-paper-2 dark:bg-night-700">
            <div class="h-full bg-brand-600 transition-all dark:bg-brand-400" style="width: {{ $percent }}%"></div>
        </div>
    </div>

    @if ($exam->shuffle_questions || $exam->shuffle_options)
        <p class="text-xs text-ink-faint dark:text-slate-500">
            Đề được trộn thứ tự {{ $exam->shuffle_questions ? 'câu hỏi' : '' }}{{ $exam->shuffle_questions && $exam->shuffle_options ? ' và ' : '' }}{{ $exam->shuffle_options ? 'đáp án' : '' }} riêng cho bạn.
        </p>
    @endif

    <div class="space-y-4">
        @foreach ($examQuestions as $index => $examQuestion)
            @php $question = $examQuestion->question; @endphp
            <div class="panel p-4 sm:p-5" wire:key="take-q-{{ $examQuestion->id }}">
                <div class="flex items-start gap-3">
                    <span class="tnum mt-0.5 w-5 shrink-0 text-sm font-semibold text-ink-faint dark:text-slate-500">{{ $index + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip chip-neutral">{{ $question->type->label() }}</span>
                            <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ (float) ($examQuestion->points ?? $question->points) }} điểm</span>
                        </div>
                        <p class="exam-protected mt-2 whitespace-pre-line leading-relaxed text-ink dark:text-slate-100">{{ $question->content }}</p>

                        @if ($question->type === \App\Enums\QuestionType::MultipleChoice)
                            <div class="mt-3 space-y-2">
                                @foreach ($optionOrder($question->options) as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-[10px] border border-rule px-3.5 py-3 text-sm transition-colors hover:bg-paper-2 has-[input:checked]:border-brand-500 has-[input:checked]:bg-brand-50 dark:border-night-700 dark:hover:bg-white/5 dark:has-[input:checked]:border-brand-400 dark:has-[input:checked]:bg-brand-500/10"
                                        wire:key="opt-{{ $option->id }}">
                                        <input type="radio" name="q-{{ $question->id }}" value="{{ $option->id }}"
                                            wire:model="answers.{{ $question->id }}.selected"
                                            class="h-4 w-4 border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                        <span class="exam-protected text-ink dark:text-slate-200">{{ $option->content }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($question->type === \App\Enums\QuestionType::TrueFalse)
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($question->type->trueFalseChoices() as $tfValue => $tfLabel)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-[10px] border border-rule px-3.5 py-3 text-sm transition-colors hover:bg-paper-2 has-[input:checked]:border-brand-500 has-[input:checked]:bg-brand-50 dark:border-night-700 dark:hover:bg-white/5 dark:has-[input:checked]:border-brand-400 dark:has-[input:checked]:bg-brand-500/10"
                                        wire:key="tf-{{ $question->id }}-{{ $tfValue }}">
                                        <input type="radio" name="tf-{{ $question->id }}" value="{{ $tfValue }}"
                                            wire:model="answers.{{ $question->id }}.text"
                                            class="h-4 w-4 border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                        <span class="exam-protected text-ink dark:text-slate-200">{{ $tfLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($question->type === \App\Enums\QuestionType::FillBlank)
                            <input type="text" class="input mt-3" wire:model.blur="answers.{{ $question->id }}.text" placeholder="Nhập đáp án ngắn">
                        @else
                            <textarea rows="5" class="input mt-3" wire:model.blur="answers.{{ $question->id }}.text" placeholder="Trình bày lời giải"></textarea>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <button type="button" wire:click="submit" wire:confirm="Nộp bài? Bạn không thể sửa sau khi nộp."
            class="btn btn-primary px-6 py-3" wire:loading.attr="disabled" wire:target="submit">
            Nộp bài
        </button>
    </div>

    @script
    <script>
        window.__awawaExamNotify = (type) => $wire.logAntiCheat(type);

        if (! window.__awawaExamHooks) {
            window.__awawaExamHooks = true;

            // Chỉ tính khi tab thực sự khuất, giữ liền tối thiểu 2 giây, và gộp
            // mọi sự kiện trong 5 giây thành một. Trên di động, blur bắn liên tục
            // khi bật bàn phím ảo, mở picker native hay chạm thanh địa chỉ — tính
            // cả những cái đó sẽ thành cảnh báo sai và làm nghẽn hàng đợi Livewire.
            const HIDDEN_GRACE_MS = 2000;
            const COALESCE_MS = 5000;
            const MIN_EVENT_GAP_MS = 30000;
            let hiddenSince = null;
            let coalesceTimer = null;
            let lastSentAt = 0;

            const flush = () => {
                coalesceTimer = null;
                const now = Date.now();

                if (now - lastSentAt < MIN_EVENT_GAP_MS) {
                    return;
                }

                lastSentAt = now;

                if (window.__awawaExamNotify) window.__awawaExamNotify('tab_hidden');
            };

            const onVisibilityChange = () => {
                if (document.hidden) {
                    hiddenSince = Date.now();
                    return;
                }

                if (hiddenSince === null) {
                    return;
                }

                const wasHiddenLongEnough = Date.now() - hiddenSince >= HIDDEN_GRACE_MS;
                hiddenSince = null;

                if (! wasHiddenLongEnough) {
                    return;
                }

                if (coalesceTimer !== null) clearTimeout(coalesceTimer);
                coalesceTimer = setTimeout(flush, COALESCE_MS);
            };

            document.addEventListener('visibilitychange', onVisibilityChange);

            // Chỉ chặn sao chép khi bắt nguồn từ vùng đề; copy từ chỗ khác
            // (kể cả từ ô trả lời ra ngoài) vẫn hoạt động để không hỏng paste.
            document.addEventListener('copy', (event) => {
                if (event.target?.closest?.('.exam-protected')) {
                    event.preventDefault();
                }
            });
        }
    </script>
    @endscript
</div>
