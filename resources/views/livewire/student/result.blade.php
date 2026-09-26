@php
    $percentage = (float) $attempt->max_score > 0 ? round(((float) $attempt->score / (float) $attempt->max_score) * 100) : 0;
@endphp

<div class="space-y-6">
    <div class="page-head">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-ink-soft hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Quay lại trang chủ</a>
            <h1 class="page-title mt-1">{{ $exam->title }}</h1>
            <p class="page-sub">Kết quả bài làm của bạn</p>
        </div>
        <span class="chip chip-neutral">{{ $attempt->status->label() }}</span>
    </div>

    <div class="panel panel-pad">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-5">
            <div>
                <p class="tnum font-serif text-4xl font-semibold leading-none text-ink dark:text-white">
                    {{ (float) $attempt->score }}<span class="text-lg font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span>
                </p>
                <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">điểm của bạn</p>
            </div>

            <div class="min-w-[200px] flex-1">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                    <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $percentage }}%"></div>
                </div>
                <p class="tnum mt-2 text-xs text-ink-soft dark:text-slate-400">
                    Trắc nghiệm và điền khuyết: {{ (float) $attempt->auto_score }} điểm
                    <span class="mx-1.5">—</span>
                    Tự luận: {{ (float) $attempt->manual_score }} điểm
                </p>
                @if ($attempt->submitted_at)
                    <p class="tnum mt-1 text-xs text-ink-faint dark:text-slate-500">Nộp lúc {{ $attempt->submitted_at->format('d/m/Y H:i') }}</p>
                @endif
                @if ($attempt->hasPendingManualGrading())
                    <p class="mt-1 text-xs font-medium text-warning dark:text-amber-400">Còn câu tự luận đang chờ giáo viên chấm.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($attempt->anti_cheat)
        <div class="alert alert-warning">
            Hệ thống ghi nhận {{ count($attempt->anti_cheat) }} lần rời màn hình trong khi làm bài.
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($examQuestions as $index => $examQuestion)
            @php
                $question = $examQuestion->question;
                $answer = $answers->get($question->id);
                $awarded = $answer?->awarded_points;
                $pending = $question->type === \App\Enums\QuestionType::Essay && $awarded === null;
            @endphp
            <div class="panel p-4 sm:p-5" wire:key="result-q-{{ $examQuestion->id }}">
                <div class="flex items-start gap-3">
                    <span class="tnum mt-0.5 w-5 shrink-0 text-sm font-semibold {{ $pending ? 'text-warning' : ($answer?->is_correct ? 'text-success' : 'text-signal') }}">
                        {{ $index + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line leading-relaxed text-ink dark:text-slate-100">{{ $question->content }}</p>

                        @if ($question->type === \App\Enums\QuestionType::MultipleChoice)
                            <ul class="mt-3 space-y-1.5">
                                @foreach ($question->options as $option)
                                    @php $selected = in_array($option->id, array_map('intval', $answer?->selected_option_ids ?? []), true); @endphp
                                    <li class="flex items-center gap-2 rounded-[10px] border px-3.5 py-2.5 text-sm
                                        {{ $option->is_correct
                                            ? 'border-success/30 bg-success-soft text-success dark:bg-success/10 dark:text-emerald-300'
                                            : ($selected ? 'border-signal/30 bg-signal-soft text-signal dark:bg-signal/10 dark:text-red-300' : 'border-rule text-ink-soft dark:border-night-700 dark:text-slate-400') }}">
                                        <span>{{ $option->content }}</span>
                                        @if ($option->is_correct) <span class="ml-auto text-xs font-medium">đáp án đúng</span> @endif
                                        @if ($selected && ! $option->is_correct) <span class="ml-auto text-xs font-medium">bạn chọn</span> @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-3 rounded-[10px] border border-rule bg-paper-2 p-3.5 dark:border-night-700 dark:bg-night-900/40">
                                <p class="text-xs font-medium text-ink-faint dark:text-slate-500">Bài làm của bạn</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink dark:text-slate-200">{{ $answer?->answer_text ?: '(bỏ trống)' }}</p>
                            </div>
                            @if ($question->answer && $question->type === \App\Enums\QuestionType::FillBlank)
                                <p class="mt-2 text-sm text-ink-soft dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->answer }}</p>
                            @endif
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($pending)
                                <span class="chip chip-warning">Chờ chấm</span>
                            @else
                                <span class="chip {{ $answer?->is_correct ? 'chip-success' : 'chip-signal' }}">{{ $answer?->is_correct ? 'Đúng' : 'Sai' }}</span>
                            @endif
                            <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ (float) ($awarded ?? 0) }} / {{ (float) ($examQuestion->points ?? $question->points) }} điểm</span>
                        </div>

                        @if ($question->explanation)
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft dark:text-slate-400"><span class="font-medium">Giải thích:</span> {{ $question->explanation }}</p>
                        @endif

                        @if ($answer?->feedback)
                            <p class="mt-3 border-l-2 border-brand-500 bg-brand-50/60 px-3.5 py-2.5 text-sm leading-relaxed text-ink dark:border-brand-400 dark:bg-brand-500/10 dark:text-slate-200">
                                <span class="font-medium">Nhận xét của giáo viên:</span> {{ $answer->feedback }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
