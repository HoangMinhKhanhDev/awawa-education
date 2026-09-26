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
            @forelse ($attempts as $attempt)
                @php
                    $statusClass = match ($attempt->status->value) {
                        'graded' => 'chip-success',
                        'submitted' => 'chip-brand',
                        default => 'chip-warning',
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="attempt-{{ $attempt->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                        {{ $attempt->student?->initials() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $attempt->student?->name }}</p>
                            <span class="chip {{ $statusClass }}">{{ $attempt->status->label() }}</span>
                            @if (count($attempt->anti_cheat ?? []) > 0)
                                <span class="chip chip-warning" title="Số lần rời màn hình">{{ count($attempt->anti_cheat) }} cảnh báo</span>
                            @endif
                        </div>
                        <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                            {{ $attempt->student?->email }}@if ($attempt->submitted_at)<span class="mx-1.5">—</span>nộp {{ $attempt->submitted_at->format('d/m/Y H:i') }}@endif
                        </p>
                    </div>
                    <span class="tnum shrink-0 text-lg font-semibold text-ink dark:text-white">
                        {{ $attempt->score === null ? '—' : (float) $attempt->score }}<span class="text-sm font-normal text-ink-faint">/{{ (float) $attempt->max_score }}</span>
                    </span>
                    <button type="button" wire:click="openGrading({{ $attempt->id }})" class="btn btn-outline px-3.5 py-2 text-xs">
                        {{ $attempt->hasPendingManualGrading() ? 'Chấm bài' : 'Xem / sửa điểm' }}
                    </button>
                </div>
            @empty
                <p class="empty">Chưa có học sinh nào làm bài.</p>
            @endforelse
        </div>
    </div>

    @if ($gradingAttempt)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
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
                    <button type="button" wire:click="saveGrading" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveGrading">Lưu điểm</span>
                        <span wire:loading wire:target="saveGrading">Đang lưu…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
