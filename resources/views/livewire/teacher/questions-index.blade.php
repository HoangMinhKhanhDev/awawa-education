<div class="space-y-6">
    <x-teacher.tabs active="questions" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Ngân hàng câu hỏi</h1>
            <p class="page-sub">Câu hỏi lưu riêng theo môn <span class="font-medium" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm câu hỏi
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel panel-pad grid gap-4 sm:grid-cols-3">
        <div>
            <label class="label" for="q-search">Tìm trong nội dung</label>
            <input id="q-search" type="search" class="input" wire:model.live.debounce.400ms="search">
        </div>
        <div>
            <label class="label" for="q-type">Dạng câu hỏi</label>
            <select id="q-type" class="input" wire:model.live="typeFilter">
                <option value="">Tất cả</option>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="q-difficulty">Độ khó</label>
            <select id="q-difficulty" class="input" wire:model.live="difficultyFilter">
                <option value="">Tất cả</option>
                @foreach ($difficulties as $difficultyOption)
                    <option value="{{ $difficultyOption->value }}">{{ $difficultyOption->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($questions as $question)
                <div class="px-5 py-4" wire:key="question-{{ $question->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="chip chip-brand">{{ $question->type->label() }}</span>
                                <span class="chip chip-neutral">{{ $question->difficulty->label() }}</span>
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ (float) $question->points }} điểm</span>
                                @if ($question->topic)
                                    <span class="text-xs text-ink-faint dark:text-slate-500">{{ $question->topic }}</span>
                                @endif
                                @if (! $question->is_active)
                                    <span class="chip chip-signal">Đang ẩn</span>
                                @endif
                            </div>
                            <p class="mt-2 whitespace-pre-line leading-relaxed text-ink dark:text-slate-200">{{ $question->content }}</p>

                            @if ($question->type->hasOptions() && $question->options->isNotEmpty())
                                <ul class="mt-2.5 grid gap-1.5 sm:grid-cols-2">
                                    @foreach ($question->options as $option)
                                        <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'font-medium text-success' : 'text-ink-soft dark:text-slate-400' }}">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $option->is_correct ? 'bg-success' : 'bg-rule-strong dark:bg-night-700' }}"></span>
                                            {{ $option->content }}
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($question->answer)
                                <p class="mt-2 text-sm text-ink-soft dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->answer }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-1.5">
                            <button type="button" wire:click="toggleActive({{ $question->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                                {{ $question->is_active ? 'Ẩn' : 'Hiện' }}
                            </button>
                            <button type="button" wire:click="openEdit({{ $question->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                            <button type="button" wire:click="delete({{ $question->id }})" wire:confirm="Xóa câu hỏi này?"
                                class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có câu hỏi nào. Thêm câu hỏi đầu tiên hoặc dùng AI để sinh.</p>
            @endforelse
        </div>
    </div>

    @if ($questions->hasPages())
        <div>{{ $questions->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa câu hỏi' : 'Thêm câu hỏi' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label" for="f-type">Dạng câu hỏi</label>
                            <select id="f-type" class="input" wire:model.live="type">
                                @foreach ($types as $typeOption)
                                    <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="f-difficulty">Độ khó</label>
                            <select id="f-difficulty" class="input" wire:model="difficulty">
                                @foreach ($difficulties as $difficultyOption)
                                    <option value="{{ $difficultyOption->value }}">{{ $difficultyOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="f-points">Điểm</label>
                            <input id="f-points" type="number" step="0.25" min="0.25" class="input" wire:model="points">
                            @error('points') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="f-content">Nội dung câu hỏi</label>
                        <textarea id="f-content" rows="3" class="input" wire:model="content" placeholder="Nhập nội dung"></textarea>
                        @error('content') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($type === 'multiple_choice')
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="label mb-0">Lựa chọn — bấm dấu tròn để chọn đáp án đúng</p>
                                <button type="button" wire:click="addOption" class="btn btn-ghost px-3 py-1.5 text-xs">
                                    <x-icon name="plus" class="h-3.5 w-3.5" /> Thêm
                                </button>
                            </div>
                            <div class="space-y-2">
                                @foreach ($options as $index => $option)
                                    <div class="flex items-center gap-2" wire:key="option-{{ $index }}">
                                        <button type="button" wire:click="markCorrect({{ $index }})"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border {{ ! empty($option['is_correct']) ? 'border-success bg-success text-white' : 'border-rule-strong text-transparent dark:border-night-700' }}"
                                            title="Đánh dấu đáp án đúng">
                                            <x-icon name="check" class="h-4 w-4" />
                                        </button>
                                        <input type="text" class="input" wire:model="options.{{ $index }}.content" placeholder="Nội dung lựa chọn">
                                        <button type="button" wire:click="removeOption({{ $index }})"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-ink-faint hover:bg-signal-soft hover:text-signal dark:hover:bg-red-500/10"
                                            title="Xóa lựa chọn">
                                            <x-icon name="x" class="h-4 w-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @error('options') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="label" for="f-answer">{{ $type === 'fill_blank' ? 'Đáp án' : 'Đáp án gợi ý / barem' }}</label>
                            <textarea id="f-answer" rows="2" class="input" wire:model="answer"></textarea>
                            @error('answer') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="f-topic">Chủ đề</label>
                            <input id="f-topic" type="text" class="input" wire:model="topic" placeholder="VD: Đại số">
                        </div>
                        <div>
                            <label class="label" for="f-tags">Thẻ (phân tách bằng dấu phẩy)</label>
                            <input id="f-tags" type="text" class="input" wire:model="tagsInput" placeholder="bpt, căn thức">
                        </div>
                    </div>

                    <div>
                        <label class="label" for="f-explanation">Lời giải / giải thích</label>
                        <textarea id="f-explanation" rows="2" class="input" wire:model="explanation"></textarea>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        Đang sử dụng
                    </label>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Lưu câu hỏi</span>
                            <span wire:loading wire:target="save">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
