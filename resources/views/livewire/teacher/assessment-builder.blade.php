@php
    $typeKey = $exam->type === \App\Enums\ExamType::Exam ? 'exams' : 'assignments';
    $isPublished = $exam->status->value === 'published';
@endphp

<div class="space-y-6">
    <x-teacher.tabs :active="$typeKey" />

    <div class="page-head">
        <div class="min-w-0">
            <a href="{{ route($exam->type->routeName()) }}" wire:navigate class="text-sm text-ink-soft hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Quay lại {{ mb_strtolower($exam->type->label()) }}</a>
            <h1 class="page-title mt-1 truncate">{{ $exam->title }}</h1>
            <p class="page-sub">
                <span class="chip chip-neutral mr-1.5">{{ $exam->status->label() }}</span>
                <span class="tnum">{{ $examQuestions->count() }} câu — {{ (float) $exam->total_points }} điểm</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (! $isPublished)
                <button type="button" wire:click="changeStatus('published')" class="btn btn-primary px-3.5 py-2 text-xs">Giao cho đội</button>
            @else
                <button type="button" wire:click="changeStatus('closed')" class="btn btn-outline px-3.5 py-2 text-xs">Đóng</button>
            @endif
            @if ($exam->status->value !== 'draft')
                <button type="button" wire:click="changeStatus('draft')" class="btn btn-ghost px-3 py-2 text-xs">Về nháp</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Cột trái --}}
        <div class="space-y-5 lg:col-span-2">
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thông tin {{ mb_strtolower($exam->type->label()) }}</h2>
                </div>
                <form wire:submit="saveMeta" class="space-y-4 p-5">
                    <div>
                        <label class="label" for="b-title">Tiêu đề</label>
                        <input id="b-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="b-description">Mô tả</label>
                        <textarea id="b-description" rows="2" class="input" wire:model="description"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="b-duration">Thời gian làm bài (phút)</label>
                            <input id="b-duration" type="number" min="1" class="input" wire:model="durationMinutes">
                            @error('durationMinutes') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="b-due">Hạn nộp / kết thúc</label>
                            <input id="b-due" type="datetime-local" class="input" wire:model="dueAt">
                        </div>
                    </div>

                    <details class="rounded-[10px] border border-rule dark:border-night-700">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-ink-soft dark:text-slate-300">Lịch mở đề và tuỳ chọn</summary>
                        <div class="grid gap-4 border-t border-rule px-4 py-4 sm:grid-cols-2 dark:border-night-700">
                            <div>
                                <label class="label" for="b-starts">Bắt đầu</label>
                                <input id="b-starts" type="datetime-local" class="input" wire:model="startsAt">
                            </div>
                            <div>
                                <label class="label" for="b-ends">Kết thúc</label>
                                <input id="b-ends" type="datetime-local" class="input" wire:model="endsAt">
                                @error('endsAt') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft sm:col-span-2 dark:text-slate-300">
                                <input type="checkbox" wire:model="shuffleQuestions" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                Trộn thứ tự câu hỏi
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft sm:col-span-2 dark:text-slate-300">
                                <input type="checkbox" wire:model="shuffleOptions" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
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

            <div class="panel">
                <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Câu hỏi trong {{ mb_strtolower($exam->type->label()) }}</h2>
                    <span class="tnum text-sm text-ink-faint dark:text-slate-500">{{ $examQuestions->count() }} câu</span>
                </div>

                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($examQuestions as $index => $examQuestion)
                        <div class="flex items-start gap-3 px-5 py-3.5" wire:key="eq-{{ $examQuestion->id }}">
                            <span class="tnum mt-0.5 w-5 shrink-0 text-sm font-semibold text-ink-faint dark:text-slate-500">{{ $index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm leading-relaxed text-ink dark:text-slate-200">{{ $examQuestion->question?->content }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2.5 text-xs text-ink-faint dark:text-slate-500">
                                    <span>{{ $examQuestion->question?->type->label() }}</span>
                                    @if ($examQuestion->section)<span>{{ $examQuestion->section->title }}</span>@endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <input type="number" step="0.25" min="0" class="input w-16 px-2 py-1 text-xs tnum"
                                    value="{{ (float) ($examQuestion->points ?? $examQuestion->question?->points) }}"
                                    wire:change="setQuestionPoints({{ $examQuestion->id }}, $event.target.value)" title="Điểm">
                                <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'up')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Lên">↑</button>
                                <button type="button" wire:click="moveQuestion({{ $examQuestion->id }}, 'down')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Xuống">↓</button>
                                <button type="button" wire:click="removeQuestion({{ $examQuestion->id }})" class="rounded-[10px] px-1.5 py-1 text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" title="Gỡ">✕</button>
                            </div>
                        </div>
                    @empty
                        <p class="empty">Chưa có câu hỏi. Chọn từ ngân hàng ở cột bên phải.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Cột phải --}}
        <div class="space-y-5">
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Phần / mục</h2>
                </div>
                <div class="space-y-2 p-5">
                    @foreach ($sections as $section)
                        <div class="flex items-center gap-1.5" wire:key="section-{{ $section->id }}">
                            <input type="text" class="input py-1.5 text-sm" wire:model.blur="sectionTitles.{{ $section->id }}">
                            <button type="button" wire:click="moveSection({{ $section->id }}, 'up')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Lên">↑</button>
                            <button type="button" wire:click="moveSection({{ $section->id }}, 'down')" class="rounded-[10px] px-1.5 py-1 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" title="Xuống">↓</button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})" class="rounded-[10px] px-1.5 py-1 text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" title="Xóa">✕</button>
                        </div>
                    @endforeach

                    <div class="flex gap-2 pt-1">
                        <input type="text" class="input py-2 text-sm" wire:model="newSectionTitle" wire:keydown.enter="addSection" placeholder="Tên phần mới">
                        <button type="button" wire:click="addSection" class="btn btn-outline px-3 py-2 text-xs">Thêm</button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thêm từ ngân hàng</h2>
                </div>
                <div class="space-y-3 p-5">
                    <input type="search" class="input" wire:model.live.debounce.400ms="questionSearch" placeholder="Tìm câu hỏi">

                    @if ($sections->isNotEmpty())
                        <div>
                            <label class="label" for="pick-section">Xếp vào phần</label>
                            <select id="pick-section" class="input" wire:model="pickSectionId">
                                <option value="">Không xếp phần</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="max-h-72 space-y-0.5 overflow-y-auto">
                        @forelse ($bankQuestions as $bankQuestion)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-[10px] px-2.5 py-2 text-sm transition-colors hover:bg-paper-2 dark:hover:bg-white/5" wire:key="bank-{{ $bankQuestion->id }}">
                                <input type="checkbox" value="{{ $bankQuestion->id }}" wire:model="selectedQuestions"
                                    class="mt-0.5 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                <span class="min-w-0">
                                    <span class="line-clamp-2 text-ink dark:text-slate-200">{{ $bankQuestion->content }}</span>
                                    <span class="mt-0.5 block text-xs text-ink-faint dark:text-slate-500">{{ $bankQuestion->type->label() }} — {{ $bankQuestion->difficulty->label() }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="empty">Không có câu hỏi phù hợp.</p>
                        @endforelse
                    </div>

                    <button type="button" wire:click="addQuestions" class="btn btn-primary w-full" @disabled(count($selectedQuestions) === 0)>
                        Thêm {{ count($selectedQuestions) ?: '' }} câu đã chọn
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
