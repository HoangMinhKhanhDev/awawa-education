@php
    $typeKey = $type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
    $titleLabel = $type->label();
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $titleLabel }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $type === \App\Enums\ExamType::Exam
                    ? 'Soạn đề từ ngân hàng câu hỏi và giao cho đội tuyển.'
                    : 'Giao bài tập về nhà kèm hạn nộp.' }}
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo {{ mb_strtolower($titleLabel) }}
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($exams as $exam)
            @php
                $statusClasses = match ($exam->status->value) {
                    'published' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                    'closed' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                    default => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                };
            @endphp
            <div class="card" wire:key="exam-{{ $exam->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-900 dark:text-white">{{ $exam->title }}</h2>
                            <span class="badge {{ $statusClasses }}">{{ $exam->status->label() }}</span>
                        </div>
                        @if ($exam->description)
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $exam->description }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-400">
                            <span>{{ $exam->exam_questions_count }} câu</span>
                            <span>· {{ (float) $exam->total_points }} điểm</span>
                            @if ($exam->duration_minutes)
                                <span>· {{ $exam->duration_minutes }} phút</span>
                            @endif
                            @if ($exam->due_at)
                                <span>· hạn {{ $exam->due_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <a href="{{ route('studio.builder', $exam) }}" wire:navigate class="btn btn-outline px-3 py-1.5 text-xs">Mở soạn</a>
                        <a href="{{ route('studio.grading', $exam) }}" wire:navigate class="btn btn-ghost px-3 py-1.5 text-xs">Bài làm</a>

                        @if ($exam->status->value === 'draft' || $exam->status->value === 'closed')
                            <button type="button" wire:click="publish({{ $exam->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Giao</button>
                        @endif
                        @if ($exam->status->value === 'published')
                            <button type="button" wire:click="close({{ $exam->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Đóng</button>
                        @endif
                        @if ($exam->status->value !== 'draft')
                            <button type="button" wire:click="reopen({{ $exam->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Về nháp</button>
                        @endif
                        <button type="button" wire:click="delete({{ $exam->id }})" wire:confirm="Xóa {{ $exam->title }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 dark:text-slate-400">Chưa có {{ mb_strtolower($titleLabel) }} nào.</div>
        @endforelse
    </div>

    @if ($exams->hasPages())
        <div>{{ $exams->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tạo {{ mb_strtolower($titleLabel) }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="create" class="space-y-4">
                    <div>
                        <label class="label" for="a-title">Tiêu đề</label>
                        <input id="a-title" type="text" class="input" wire:model="title" placeholder="Kiểm tra chuyên đề 1">
                        @error('title') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="a-description">Mô tả</label>
                        <textarea id="a-description" rows="3" class="input" wire:model="description"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
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
