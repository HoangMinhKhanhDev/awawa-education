@php
    $percentage = (float) $attempt->max_score > 0 ? round(((float) $attempt->score / (float) $attempt->max_score) * 100) : 0;
@endphp

<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">← Trang chủ</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $exam->title }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kết quả bài làm của bạn</p>
        </div>
        <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $attempt->status->label() }}</span>
    </header>

    <div class="card flex flex-wrap items-center gap-6">
        <div class="text-center">
            <p class="text-4xl font-extrabold text-brand-600 dark:text-brand-400">{{ (float) $attempt->score }}</p>
            <p class="text-xs text-slate-400">trên {{ (float) $attempt->max_score }} điểm</p>
        </div>
        <div class="min-w-[180px] flex-1">
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $percentage }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                Tự động: {{ (float) $attempt->auto_score }} · Chấm tay: {{ (float) $attempt->manual_score }}
                @if ($attempt->submitted_at) · nộp {{ $attempt->submitted_at->format('d/m/Y H:i') }} @endif
            </p>
            @if ($attempt->hasPendingManualGrading())
                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">Còn câu tự luận đang chờ giáo viên chấm.</p>
            @endif
        </div>
    </div>

    @if ($attempt->anti_cheat)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Hệ thống ghi nhận {{ count($attempt->anti_cheat) }} sự kiện rời màn hình trong khi làm bài.
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
            <div class="card" wire:key="result-q-{{ $examQuestion->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white
                        {{ $pending ? 'bg-amber-500' : ($answer?->is_correct ? 'bg-emerald-500' : 'bg-red-500') }}">
                        {{ $index + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line text-slate-800 dark:text-slate-100">{{ $question->content }}</p>

                        @if ($question->type === \App\Enums\QuestionType::MultipleChoice)
                            <ul class="mt-3 space-y-1.5">
                                @foreach ($question->options as $option)
                                    @php
                                        $selected = in_array($option->id, array_map('intval', $answer?->selected_option_ids ?? []), true);
                                    @endphp
                                    <li class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm
                                        {{ $option->is_correct ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : ($selected ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'text-slate-600 dark:text-slate-300') }}">
                                        <span>{{ $option->content }}</span>
                                        @if ($option->is_correct) <span class="text-xs font-semibold">(đáp án đúng)</span> @endif
                                        @if ($selected && ! $option->is_correct) <span class="text-xs font-semibold">(bạn chọn)</span> @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-white/5">
                                <p class="text-xs font-medium text-slate-400">Bài làm của bạn</p>
                                <p class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $answer?->answer_text ?: '(bỏ trống)' }}</p>
                            </div>
                            @if ($question->answer && $question->type === \App\Enums\QuestionType::FillBlank)
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->answer }}</p>
                            @endif
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            @if ($pending)
                                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Chờ chấm</span>
                            @else
                                <span class="badge {{ $answer?->is_correct ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">
                                    {{ $answer?->is_correct ? 'Đúng' : 'Sai' }}
                                </span>
                            @endif
                            <span class="text-slate-400">{{ (float) ($awarded ?? 0) }} / {{ (float) ($examQuestion->points ?? $question->points) }} điểm</span>
                        </div>

                        @if ($question->explanation)
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><span class="font-medium">Giải thích:</span> {{ $question->explanation }}</p>
                        @endif

                        @if ($answer?->feedback)
                            <p class="mt-2 rounded-lg bg-brand-50 p-2 text-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
                                <span class="font-medium">Nhận xét của giáo viên:</span> {{ $answer->feedback }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
