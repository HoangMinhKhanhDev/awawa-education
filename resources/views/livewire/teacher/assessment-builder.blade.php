@php
    $typeKey = $exam->type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
    $isPublished = $exam->status->value === 'published';
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route($exam->type->routeName()) }}" wire:navigate class="text-sm text-slate-500 hover:text-brand-600 dark:text-slate-400">
                ← {{ $exam->type->label() }}
            </a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $exam->title }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $exam->status->label() }}</span>
                <span class="text-slate-400">{{ $examQuestions->count() }} câu · {{ (float) $exam->total_points }} điểm</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (! $isPublished)
                <button type="button" wire:click="changeStatus('published')" class="btn btn-primary px-3 py-1.5 text-xs">Giao cho đội</button>
            @else
                <button type="button" wire:click="changeStatus('closed')" class="btn btn-ghost px-3 py-1.5 text-xs">Đóng</button>
            @endif
            @if ($exam->status->value !== 'draft')
                <button type="button" wire:click="changeStatus('draft')" class="btn btn-ghost px-3 py-1.5 text-xs">Về nháp</button>
            @endif
        </div>
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

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Cột trái --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thông tin {{ mb_strtolower($exam->type->label()) }}</h2>
                <form wire:submit="saveMeta" class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="b-title">Tiêu đề</label>
                        <input id="b-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="b-description">Mô tả</label>
                        <textarea id="b-description" rows="2" class="input" wire:model="description"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="b-duration">Thời gian làm bài (phút)</label>
                            <input id="b-duration" type="number" min="1" class="input" wire:model="durationMinutes">
                            @error('durationMinutes') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="b-due">Hạn nộp / kết thúc</label>
                            <input id="b-due" type="datetime-local" class="input" wire:model="dueAt">
                        </div>
                    </div>
                    <details class="rounded-xl border border-slate-200 p-3 dark:border-white/10">
                        <summary class="cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-300">Lịch mở đề & tuỳ chọn</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="b-starts">Bắt đầu</label>
                                <input id="b-starts" type="datetime-local" class="input" wire:model="startsAt">
                            </div>
                            <div>
                                <label class="label" for="b-ends">Kết thúc</label>
                                <input id="b-ends" type="datetime-local" class="input" wire:model="endsAt">
                                @error('endsAt') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-3 space-y-2">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" wire:model="shuffleQuestions" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                                Trộn thứ tự câu hỏi
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" wire:model="shuffleOptions" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                                Trộn thứ tự đáp án
                            </label>
                        </div>
                    </details>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveMeta">Lưu thông tin</span>
                            <span wire:loading wire:target="saveMeta">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Câu hỏi trong {{ mb_strtolower($exam->type->label()) }}</h2>
                    <span class="text-sm text-slate-400">{{ $examQuestions->count() }} câu</span>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($examQuestions as $index => $examQuestion)
                        <div class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 dark:border-white/10" wire:key="eq-{{ $examQuestion->id }}">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ $index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm text-slate-700 dark:text-slate-200">{{ $examQuestion->question?->content }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                    <span>{{ $examQuestion->question?->type->label() }}</span>
                                    @if ($examQuestion->section)
                                        <span>· {{ $examQuestion->section->title }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <input type="number" step="0.25" min="0" class="input w-16 px-2 py-1 text-xs"
                                    value="{{ (float) ($examQuestion->points ?? $examQuestion->question?->points) }}"
                                    wire:change="setQuestionPoints({{ $examQuestion->id }}, $event.target.value)" title="Điểm">
                                <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'up')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5" title="Lên">▲</button>
                                <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'down')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5" title="Xuống">▼</button>
                                <button type="button" wire:click="removeQuestion({{ $examQuestion->id }})" class="rounded-lg p-1 text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10" title="Gỡ">✕</button>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-400">Chưa có câu hỏi. Chọn từ ngân hàng bên phải.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Cột phải --}}
        <div class="space-y-4">
            <div class="card">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Phần / mục</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($sections as $section)
                        <div class="flex items-center gap-1" wire:key="section-{{ $section->id }}">
                            <input type="text" class="input py-1.5 text-sm" wire:model.blur="sectionTitles.{{ $section->id }}">
                            <button type="button" wire:click="moveSection({{ $section->id }}, 'up')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5" title="Lên">▲</button>
                            <button type="button" wire:click="moveSection({{ $section->id }}, 'down')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5" title="Xuống">▼</button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})" class="rounded-lg p-1 text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10" title="Xóa">✕</button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex gap-2">
                    <input type="text" class="input py-2 text-sm" wire:model="newSectionTitle" wire:keydown.enter="addSection" placeholder="Tên phần mới">
                    <button type="button" wire:click="addSection" class="btn btn-outline px-3 py-2 text-xs">Thêm</button>
                </div>
            </div>

            <div class="card">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thêm từ ngân hàng</h2>
                <input type="search" class="input mt-3" wire:model.live.debounce.400ms="questionSearch" placeholder="Tìm câu hỏi...">

                @if ($sections->isNotEmpty())
                    <div class="mt-3">
                        <label class="label" for="pick-section">Xếp vào phần</label>
                        <select id="pick-section" class="input" wire:model="pickSectionId">
                            <option value="">Không xếp phần</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
                    @forelse ($bankQuestions as $bankQuestion)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg p-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5" wire:key="bank-{{ $bankQuestion->id }}">
                            <input type="checkbox" value="{{ $bankQuestion->id }}" wire:model="selectedQuestions"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            <span class="min-w-0">
                                <span class="line-clamp-2 text-slate-700 dark:text-slate-200">{{ $bankQuestion->content }}</span>
                                <span class="text-xs text-slate-400">{{ $bankQuestion->type->label() }} · {{ $bankQuestion->difficulty->label() }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Không có câu hỏi phù hợp.</p>
                    @endforelse
                </div>

                <button type="button" wire:click="addQuestions" class="btn btn-primary mt-3 w-full" @if (count($selectedQuestions) === 0) disabled @endif>
                    Thêm {{ count($selectedQuestions) ?: '' }} câu đã chọn
                </button>
            </div>
        </div>
    </div>
</div>
