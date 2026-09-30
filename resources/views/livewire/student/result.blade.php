@php
    $percentage = $attempt->percent() ?? 0;
    $delta = $previousAttempt?->percent() === null ? null : $percentage - $previousAttempt->percent();
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

    @if ($attempts->count() > 1 || $canRetake)
        <div class="panel panel-pad flex flex-wrap items-center gap-x-4 gap-y-3">
            <div class="flex flex-wrap items-center gap-2">
                @if ($maxAttempts > 0)
                    <span class="tnum text-sm font-medium text-ink dark:text-slate-200">Lần {{ $attempt->attempt_no }}/{{ $maxAttempts }}</span>
                @else
                    <span class="tnum text-sm font-medium text-ink dark:text-slate-200">Lần {{ $attempt->attempt_no }}</span>
                @endif

                @if ($delta !== null)
                    <span class="tnum text-xs {{ $delta > 0 ? 'text-success dark:text-emerald-400' : ($delta < 0 ? 'text-signal dark:text-red-400' : 'text-ink-faint dark:text-slate-500') }}">
                        {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 2) }} điểm so với lần trước
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($attempts as $row)
                    @php $active = $row->id === $attempt->id; @endphp
                    <a
                        href="{{ route('student.result', ['exam' => $exam, 'lan' => $row->attempt_no]) }}"
                        wire:navigate
                        aria-current="{{ $active ? 'true' : 'false' }}"
                        class="tnum rounded-full border px-3 py-1 text-xs font-medium transition-colors
                            {{ $active
                                ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                                : 'border-rule text-ink-soft hover:bg-paper-2 dark:border-night-700 dark:text-slate-400 dark:hover:bg-white/5' }}"
                    >
                        L{{ $row->attempt_no }} · {{ number_format($row->percent() ?? 0, 0) }}%
                    </a>
                @endforeach
            </div>

            @if ($canRetake)
                <a href="{{ route('student.take', $exam) }}" wire:navigate class="btn btn-outline ml-auto">
                    <x-icon name="refresh" class="h-4 w-4" />
                    Làm lại
                    @if ($remainingAttempts > 0)
                        <span class="tnum opacity-70">(còn {{ $remainingAttempts }} lượt)</span>
                    @endif
                </a>
            @endif
        </div>
    @endif

    <div class="panel panel-pad">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-5">
            <div>
                <p class="tnum font-serif text-4xl font-semibold leading-none text-ink dark:text-white">
                    {{ (float) $attempt->score }}<span class="text-lg font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span>
                </p>
                <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">điểm của bạn</p>
            </div>

            <div class="min-w-[200px] flex-1">
                <div
                    class="h-1.5 w-full overflow-hidden rounded-full bg-paper-2 dark:bg-night-700"
                    role="progressbar"
                    aria-label="Tỉ lệ điểm"
                    aria-valuenow="{{ (int) $percentage }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
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
                @if ($attempt->time_spent_seconds)
                    <p class="tnum mt-1 text-xs text-ink-faint dark:text-slate-500">
                        Thời gian làm: {{ intdiv($attempt->time_spent_seconds, 60) }} phút {{ $attempt->time_spent_seconds % 60 }} giây
                    </p>
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
        @php $questionNo = 0; @endphp
        @foreach ($questionGroups as $group)
            @if ($group['section'] !== null && (filled($group['section']->title) || filled($group['section']->instructions)))
                <div class="panel border-brand-300 p-4 sm:p-5 dark:border-brand-500/40">
                    @if (filled($group['section']->title))
                        <h2 class="text-sm font-semibold uppercase text-ink dark:text-white">{{ $group['section']->title }}</h2>
                    @endif
                    @if (filled($group['section']->instructions))
                        <p class="mt-1.5 whitespace-pre-line text-sm italic leading-relaxed text-ink-soft dark:text-slate-300">{{ $group['section']->instructions }}</p>
                    @endif
                </div>
            @endif
            @foreach ($group['items'] as $examQuestion)
            @php
                $question = $examQuestion->question;
                $answer = $answers->get($question->id);
                $awarded = $answer?->awarded_points;
                $pending = $question->type === \App\Enums\QuestionType::Essay && $awarded === null;
                $questionNo++;
            @endphp
            <div class="panel p-4 sm:p-5" wire:key="result-q-{{ $examQuestion->id }}">
                <div class="flex items-start gap-3">
                    <span class="tnum mt-0.5 w-5 shrink-0 text-sm font-semibold {{ $pending ? 'text-warning' : ($answer?->is_correct ? 'text-success' : 'text-signal') }}">
                        {{ $questionNo }}
                    </span>
                    <div class="min-w-0 flex-1">
                        @if ($question->type === \App\Enums\QuestionType::TrueFalseCluster)
                            <div class="notebook-markdown leading-relaxed text-ink dark:text-slate-100">{!! \Illuminate\Support\Str::markdown((string) $question->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        @else
                            <p class="whitespace-pre-line leading-relaxed text-ink dark:text-slate-100">{{ $question->content }}</p>
                        @endif

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
                        @elseif ($question->type === \App\Enums\QuestionType::TrueFalseCluster)
                            @php
                                $subs = array_values((array) ($answer?->sub_answers ?? []));
                                $clusterCorrect = 0;
                                foreach ($question->options as $subIndex => $sub) {
                                    if (\App\Enums\QuestionType::normalizeTruthy((string) ($subs[$subIndex] ?? '')) !== null
                                        && (\App\Enums\QuestionType::normalizeTruthy((string) ($subs[$subIndex] ?? '')) === \App\Enums\QuestionType::TRUE) === (bool) $sub->is_correct) {
                                        $clusterCorrect++;
                                    }
                                }
                            @endphp
                            <ul class="mt-3 space-y-1.5">
                                @foreach ($question->options as $subIndex => $sub)
                                    @php
                                        $given = \App\Enums\QuestionType::normalizeTruthy((string) ($subs[$subIndex] ?? ''));
                                        $expectedTrue = (bool) $sub->is_correct;
                                        $subOk = $given !== null && ($given === \App\Enums\QuestionType::TRUE) === $expectedTrue;
                                    @endphp
                                    <li class="flex items-center gap-2 rounded-[10px] border px-3.5 py-2.5 text-sm
                                        {{ $subOk ? 'border-success/30 bg-success-soft dark:bg-success/10' : 'border-rule dark:border-night-700' }}">
                                        <span class="tnum w-6 shrink-0 font-semibold text-ink-faint dark:text-slate-500">{{ chr(97 + $subIndex) }})</span>
                                        <span class="min-w-0 flex-1 text-ink dark:text-slate-200">{{ $sub->content }}</span>
                                        <span class="tnum shrink-0 text-xs {{ $subOk ? 'font-medium text-success dark:text-emerald-300' : 'text-signal dark:text-red-300' }}">
                                            {{ $given === null ? 'bỏ trống' : ($given === \App\Enums\QuestionType::TRUE ? 'Đúng' : 'Sai') }}{{ $subOk ? '' : ' (đáp án: '.($expectedTrue ? 'Đúng' : 'Sai').')' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-sm text-ink-soft dark:text-slate-400">
                                Đúng {{ $clusterCorrect }}/4 mệnh đề
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">— nấc điểm: 0 · 0.25 · 0.5 · 1.0 ({{ (float) ($examQuestion->points ?? $question->points) }} điểm)</span>
                            </p>
                        @else
                            <div class="mt-3 rounded-[10px] border border-rule bg-paper-2 p-3.5 dark:border-night-700 dark:bg-night-900/40">
                                <p class="text-xs font-medium text-ink-faint dark:text-slate-500">
                                    @if ($question->type === \App\Enums\QuestionType::TrueFalse)
                                        Bạn chọn
                                    @else
                                        Bài làm của bạn
                                    @endif
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink dark:text-slate-200">
                                    @if ($question->type === \App\Enums\QuestionType::TrueFalse)
                                        {{ $question->type->trueFalseLabel($answer?->answer_text) }}
                                    @else
                                        {{ $answer?->answer_text ?: '(bỏ trống)' }}
                                    @endif
                                </p>
                            </div>
                            @if ($question->answer && $question->type === \App\Enums\QuestionType::FillBlank)
                                <p class="mt-2 text-sm text-ink-soft dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->answer }}</p>
                            @elseif ($question->answer && $question->type === \App\Enums\QuestionType::TrueFalse)
                                <p class="mt-2 text-sm text-ink-soft dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->type->trueFalseLabel($question->answer) }}</p>
                            @endif
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($pending)
                                <span class="chip chip-warning">Chờ chấm</span>
                            @elseif ($question->type === \App\Enums\QuestionType::TrueFalseCluster)
                                <span class="chip {{ $clusterCorrect === 4 ? 'chip-success' : ($clusterCorrect >= 2 ? 'chip-brand' : 'chip-signal') }}">Đúng {{ $clusterCorrect }}/4</span>
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
        @endforeach
    </div>
</div>
