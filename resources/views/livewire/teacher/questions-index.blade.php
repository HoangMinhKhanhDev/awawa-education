<div class="space-y-6">
    <x-teacher.tabs active="questions" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Ngân hàng câu hỏi</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Câu hỏi được lưu riêng theo môn <span class="font-semibold" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm câu hỏi
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="card grid gap-3 sm:grid-cols-3">
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

    <div class="space-y-3">
        @forelse ($questions as $question)
            <div class="card" wire:key="question-{{ $question->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $question->type->label() }}</span>
                            <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $question->difficulty->label() }}</span>
                            <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ (float) $question->points }} điểm</span>
                            @if (! $question->is_active)
                                <span class="badge bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">Đang ẩn</span>
                            @endif
                            @if ($question->topic)
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $question->topic }}</span>
                            @endif
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $question->content }}</p>

                        @if ($question->type->hasOptions() && $question->options->isNotEmpty())
                            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                                @foreach ($question->options as $option)
                                    <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full {{ $option->is_correct ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-white/20' }}"></span>
                                        {{ $option->content }}
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($question->answer)
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $question->answer }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="toggleActive({{ $question->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $question->is_active ? 'Ẩn' : 'Hiện' }}
                        </button>
                        <button type="button" wire:click="openEdit({{ $question->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $question->id }})" wire:confirm="Xóa câu hỏi này?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 dark:text-slate-400">Chưa có câu hỏi nào.</div>
        @endforelse
    </div>

    @if ($questions->hasPages())
        <div>{{ $questions->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingId ? 'Sửa câu hỏi' : 'Thêm câu hỏi' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
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
                            @error('points') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="f-content">Nội dung câu hỏi</label>
                        <textarea id="f-content" rows="3" class="input" wire:model="content" placeholder="Nhập nội dung..."></textarea>
                        @error('content') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($type === 'multiple_choice')
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="label mb-0">Lựa chọn (chọn một đáp án đúng)</p>
                                <button type="button" wire:click="addOption" class="btn btn-ghost px-3 py-1.5 text-xs">
                                    <x-icon name="plus" class="h-3.5 w-3.5" /> Thêm
                                </button>
                            </div>
                            <div class="space-y-2">
                                @foreach ($options as $index => $option)
                                    <div class="flex items-center gap-2" wire:key="option-{{ $index }}">
                                        <button type="button" wire:click="markCorrect({{ $index }})"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border {{ ! empty($option['is_correct']) ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 text-transparent dark:border-white/20' }}"
                                            title="Đánh dấu đáp án đúng">
                                            <x-icon name="check" class="h-4 w-4" />
                                        </button>
                                        <input type="text" class="input" wire:model="options.{{ $index }}.content" placeholder="Nội dung lựa chọn">
                                        <button type="button" wire:click="removeOption({{ $index }})"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                            title="Xóa lựa chọn">
                                            <x-icon name="x" class="h-4 w-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @error('options') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="label" for="f-answer">{{ $type === 'fill_blank' ? 'Đáp án' : 'Đáp án gợi ý / barem' }}</label>
                            <textarea id="f-answer" rows="2" class="input" wire:model="answer"></textarea>
                            @error('answer') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
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

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                        Đang sử dụng
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
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
