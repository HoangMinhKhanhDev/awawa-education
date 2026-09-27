@php
    $typeKey = $exam->type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <div class="page-head">
        <div class="min-w-0">
            <a href="{{ route($exam->type->routeName()) }}" wire:navigate class="text-sm text-ink-soft hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Quay lại {{ mb_strtolower($exam->type->label()) }}</a>
            <h1 class="page-title mt-1 truncate">Bài làm: {{ $exam->title }}</h1>
            <p class="page-sub">
                <span class="tnum">{{ $attempts->count() }} học sinh đã làm — {{ (float) $exam->total_points }} điểm tối đa</span>
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($attempts as $studentAttempts)
                @php
                    $latest = $studentAttempts->first();
                    $older = $studentAttempts->slice(1);
                    $statusClass = match ($latest->status->value) {
                        'graded' => 'chip-success',
                        'submitted' => 'chip-brand',
                        default => 'chip-warning',
                    };
                @endphp
                <div wire:key="student-{{ $latest->student_id }}">
                    <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                            {{ $latest->student?->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-ink dark:text-slate-100">{{ $latest->student?->name }}</p>
                                <span class="chip {{ $statusClass }}">{{ $latest->status->label() }}</span>
                                @if ($studentAttempts->count() > 1)
                                    <span class="chip chip-neutral">Lần {{ $latest->attempt_no }}/{{ $exam->maxAttempts() }}</span>
                                @endif
                                @if (count($latest->anti_cheat ?? []) > 0)
                                    <span class="chip chip-warning" title="Số lần rời màn hình">{{ count($latest->anti_cheat) }} cảnh báo</span>
                                @endif
                            </div>
                            <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                {{ $latest->student?->email }}@if ($latest->submitted_at)<span class="mx-1.5">—</span>nộp {{ $latest->submitted_at->format('d/m/Y H:i') }}@endif
                            </p>
                        </div>
                        <span class="tnum shrink-0 text-lg font-semibold text-ink dark:text-white">
                            {{ $latest->score === null ? '—' : (float) $latest->score }}<span class="text-sm font-normal text-ink-faint">/{{ (float) $latest->max_score }}</span>
                        </span>
                        <button type="button" wire:click="openGrading({{ $latest->id }})" class="btn btn-outline px-3.5 py-2 text-xs">
                            {{ $latest->hasPendingManualGrading() ? 'Chấm bài' : 'Xem / sửa điểm' }}
                        </button>
                    </div>

                    @if ($older->isNotEmpty())
                        <details class="border-t border-rule dark:border-night-700">
                            <summary class="cursor-pointer px-5 py-2.5 text-xs font-medium text-ink-soft dark:text-slate-300">
                                Xem {{ $older->count() }} lần làm trước
                            </summary>
                            <div class="divide-y divide-rule border-t border-rule dark:divide-night-700 dark:border-night-700">
                                @foreach ($older as $previous)
                                    <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                                        <span class="chip chip-neutral">Lần {{ $previous->attempt_no }}</span>
                                        <span class="tnum text-xs text-ink-faint dark:text-slate-500">
                                            {{ $previous->status->label() }}
                                            @if ($previous->submitted_at)<span class="mx-1.5">—</span>{{ $previous->submitted_at->format('d/m/Y H:i') }}@endif
                                        </span>
                                        <span class="tnum ml-auto text-sm font-semibold text-ink-soft dark:text-slate-200">
                                            {{ $previous->score === null ? '—' : (float) $previous->score }}<span class="text-xs font-normal text-ink-faint">/{{ (float) $previous->max_score }}</span>
                                        </span>
                                        <button type="button" wire:click="openGrading({{ $previous->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                                            Xem
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            @empty
                <p class="empty">Chưa có học sinh nào làm bài.</p>
            @endforelse
        </div>
    </div>

    @if ($gradingAttempt)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/60 p-0 sm:items-center sm:p-4">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $gradingAttempt->student?->name }}</h2>
                        <p class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $gradingAttempt->status->label() }} — {{ (float) $gradingAttempt->auto_score }} điểm tự động</p>
                    </div>
                    <button type="button" wire:click="closeGrading" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach ($gradingAttempt->answers as $answer)
                        @php $question = $answer->question; @endphp
                        <div class="rounded-[10px] border border-rule p-4 dark:border-night-700" wire:key="ga-{{ $answer->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="chip chip-neutral">{{ $question?->type->label() }}</span>
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">tối đa {{ (float) $question?->points }} điểm</span>
                                @if ($question?->type !== \App\Enums\QuestionType::Essay)
                                    <span class="chip {{ $answer->is_correct ? 'chip-success' : 'chip-signal' }}">{{ $answer->is_correct ? 'Đúng' : 'Sai' }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-ink dark:text-slate-100">{{ $question?->content }}</p>

                            <div class="mt-2.5 rounded-[10px] border border-rule bg-paper-2 p-3 dark:border-night-700 dark:bg-night-900/40">
                                <p class="text-xs font-medium text-ink-faint dark:text-slate-500">Bài làm</p>
                                @if ($question?->type === \App\Enums\QuestionType::MultipleChoice)
                                    @php $selectedIds = array_map('intval', $answer->selected_option_ids ?? []); @endphp
                                    <ul class="mt-1.5 space-y-1">
                                        @foreach ($question->options as $option)
                                            <li class="text-sm {{ in_array($option->id, $selectedIds, true) ? 'font-medium text-brand-700 dark:text-brand-300' : 'text-ink-soft dark:text-slate-400' }}">
                                                {{ $option->content }}
                                                @if ($option->is_correct) <span class="text-xs text-success">đáp án đúng</span> @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($question?->type === \App\Enums\QuestionType::TrueFalse)
                                    <p class="mt-1.5 text-sm text-ink dark:text-slate-200">
                                        Học sinh chọn: <span class="font-medium">{{ $question->type->trueFalseLabel($answer->answer_text) }}</span>
                                        <span class="text-xs text-ink-faint dark:text-slate-500">
                                            — đáp án: {{ $question->type->trueFalseLabel($question->answer) }}
                                        </span>
                                    </p>
                                @else
                                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink dark:text-slate-200">{{ $answer->answer_text ?: '(bỏ trống)' }}</p>
                                @endif
                            </div>

                            @if ($question?->type === \App\Enums\QuestionType::Essay)
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="label" for="ga-points-{{ $answer->id }}">Điểm</label>
                                        <input id="ga-points-{{ $answer->id }}" type="number" step="0.25" min="0" max="{{ (float) $question->points }}"
                                            class="input tnum" wire:model="manual.{{ $answer->id }}.points">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="label" for="ga-feedback-{{ $answer->id }}">Nhận xét</label>
                                        <input id="ga-feedback-{{ $answer->id }}" type="text" class="input" wire:model="manual.{{ $answer->id }}.feedback" placeholder="Nhận xét gửi tới học sinh">
                                    </div>
                                </div>
                            @elseif ($answer->is_correct === false || $answer->is_correct === true)
                                <p class="tnum mt-2 text-xs text-ink-faint dark:text-slate-500">Điểm tự động: {{ (float) $answer->awarded_points }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeGrading" class="btn btn-ghost">Đóng</button>
                    <button type="button" wire:click="saveGrading" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveGrading">
                        <span wire:loading.remove wire:target="saveGrading">Lưu điểm</span>
                        <span wire:loading wire:target="saveGrading">Đang lưu…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
