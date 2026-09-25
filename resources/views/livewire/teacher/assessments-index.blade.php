@php
    $typeKey = $type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
    $titleLabel = $type->label();
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $titleLabel }}</h1>
            <p class="page-sub">
                {{ $type === \App\Enums\ExamType::Exam
                    ? 'Soạn đề từ ngân hàng câu hỏi rồi giao cho đội tuyển.'
                    : 'Giao bài tập về nhà kèm hạn nộp.' }}
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo {{ mb_strtolower($titleLabel) }}
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($exams as $exam)
                @php
                    $statusClass = match ($exam->status->value) {
                        'published' => 'chip-success',
                        'closed' => 'chip-signal',
                        default => 'chip-neutral',
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="exam-{{ $exam->id }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $exam->title }}</p>
                            <span class="chip {{ $statusClass }}">{{ $exam->status->label() }}</span>
                        </div>
                        @if ($exam->description)
                            <p class="mt-0.5 line-clamp-1 text-sm text-ink-soft dark:text-slate-400">{{ $exam->description }}</p>
                        @endif
                        <p class="tnum mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                            <span>{{ $exam->exam_questions_count }} câu</span>
                            <span>{{ (float) $exam->total_points }} điểm</span>
                            @if ($exam->duration_minutes)<span>{{ $exam->duration_minutes }} phút</span>@endif
                            @if ($exam->due_at)<span>hạn {{ $exam->due_at->format('d/m/Y H:i') }}</span>@endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="{{ route('studio.builder', $exam) }}" wire:navigate class="btn btn-primary px-3.5 py-2 text-xs">Mở soạn</a>
                        <a href="{{ route('studio.grading', $exam) }}" wire:navigate class="btn btn-outline px-3.5 py-2 text-xs">Bài làm</a>

                        @if (in_array($exam->status->value, ['draft', 'closed'], true))
                            <button type="button" wire:click="publish({{ $exam->id }})" class="btn btn-ghost px-3 py-2 text-xs">Giao</button>
                        @endif
                        @if ($exam->status->value === 'published')
                            <button type="button" wire:click="close({{ $exam->id }})" class="btn btn-ghost px-3 py-2 text-xs">Đóng</button>
                        @endif
                        @if ($exam->status->value !== 'draft')
                            <button type="button" wire:click="reopen({{ $exam->id }})" class="btn btn-ghost px-3 py-2 text-xs">Về nháp</button>
                        @endif
                        <button type="button" wire:click="delete({{ $exam->id }})" wire:confirm="Xóa {{ $exam->title }}?"
                            class="btn btn-ghost px-3 py-2 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có {{ mb_strtolower($titleLabel) }} nào.</p>
            @endforelse
        </div>
    </div>

    @if ($exams->hasPages())
        <div>{{ $exams->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">Tạo {{ mb_strtolower($titleLabel) }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="create" class="space-y-4">
                    <div>
                        <label class="label" for="a-title">Tiêu đề</label>
                        <input id="a-title" type="text" class="input" wire:model="title" placeholder="Kiểm tra chuyên đề 1">
                        @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="a-description">Mô tả</label>
                        <textarea id="a-description" rows="3" class="input" wire:model="description"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="create">Tạo và soạn</span>
                            <span wire:loading wire:target="create">Đang tạo…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
