@php
    $typeKey = $exam->type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route($exam->type->routeName()) }}" wire:navigate class="text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
                ← {{ $exam->type->label() }}
            </a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Bài làm: {{ $exam->title }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $attempts->count() }} học sinh đã làm · {{ (float) $exam->total_points }} điểm tối đa</p>
        </div>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse ($attempts as $attempt)
                @php
                    $statusClasses = match ($attempt->status->value) {
                        'graded' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                        'submitted' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-3 p-4" wire:key="attempt-{{ $attempt->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                        {{ $attempt->student?->initials() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $attempt->student?->name }}</p>
                            <span class="badge {{ $statusClasses }}">{{ $attempt->status->label() }}</span>
                            @if (count($attempt->anti_cheat ?? []) > 0)
                                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" title="Số lần rời màn hình">
                                    {{ count($attempt->anti_cheat) }} cảnh báo
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400">
                            {{ $attempt->student?->email }}
                            @if ($attempt->submitted_at) · nộp {{ $attempt->submitted_at->format('d/m/Y H:i') }} @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-brand-600 dark:text-brand-400">{{ $attempt->score === null ? '—' : (float) $attempt->score }}</p>
                        <p class="text-xs text-slate-400">/ {{ (float) $attempt->max_score }}</p>
                    </div>
                    <button type="button" wire:click="openGrading({{ $attempt->id }})" class="btn btn-outline px-3 py-1.5 text-xs">
                        {{ $attempt->hasPendingManualGrading() ? 'Chấm bài' : 'Xem / sửa điểm' }}
                    </button>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Chưa có học sinh nào làm bài.</div>
            @endforelse
        </div>
    </div>

    @if ($gradingAttempt)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $gradingAttempt->student?->name }}</h2>
                        <p class="text-xs text-slate-400">{{ $gradingAttempt->status->label() }} · {{ (float) $gradingAttempt->auto_score }} điểm tự động</p>
                    </div>
                    <button type="button" wire:click="closeGrading" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    @foreach ($gradingAttempt->answers as $answer)
                        @php $question = $answer->question; @endphp
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10" wire:key="ga-{{ $answer->id }}">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                <span>{{ $question?->type->label() }}</span>
                                <span>· tối đa {{ (float) $question?->points }} điểm</span>
                                @if ($question?->type !== \App\Enums\QuestionType::Essay)
                                    <span class="badge {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">
                                        {{ $answer->is_correct ? 'Đúng' : 'Sai' }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-slate-800 dark:text-slate-100">{{ $question?->content }}</p>

                            <div class="mt-2 rounded-lg bg-slate-50 p-3 text-sm dark:bg-white/5">
                                <p class="text-xs font-medium text-slate-400">Bài làm</p>
                                @if ($question?->type === \App\Enums\QuestionType::MultipleChoice)
                                    @php
                                        $selectedIds = array_map('intval', $answer->selected_option_ids ?? []);
                                    @endphp
                                    <ul class="mt-1 space-y-1">
                                        @foreach ($question->options as $option)
                                            <li class="{{ in_array($option->id, $selectedIds, true) ? 'font-semibold text-brand-700 dark:text-brand-300' : 'text-slate-500 dark:text-slate-400' }}">
                                                {{ $option->content }}
                                                @if ($option->is_correct) <span class="text-xs text-emerald-600">(đúng)</span> @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $answer->answer_text ?: '(bỏ trống)' }}</p>
                                @endif
                            </div>

                            @if ($question?->type === \App\Enums\QuestionType::Essay)
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="label" for="ga-points-{{ $answer->id }}">Điểm</label>
                                        <input id="ga-points-{{ $answer->id }}" type="number" step="0.25" min="0" max="{{ (float) $question->points }}"
                                            class="input" wire:model="manual.{{ $answer->id }}.points">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="label" for="ga-feedback-{{ $answer->id }}">Nhận xét</label>
                                        <input id="ga-feedback-{{ $answer->id }}" type="text" class="input" wire:model="manual.{{ $answer->id }}.feedback" placeholder="Nhận xét cho học sinh">
                                    </div>
                                </div>
                            @elseif ($answer->is_correct === false || $answer->is_correct === true)
                                <p class="mt-2 text-xs text-slate-400">Điểm tự động: {{ (float) $answer->awarded_points }}</p>
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
