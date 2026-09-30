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
                            <label class="label" for="b-weight">Hệ số đề</label>
                            <input id="b-weight" type="number" min="0.5" max="5" step="0.5" class="input" wire:model="examWeight">
                            @error('examWeight') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Đề quan trọng để 2–3, bài tập thường để 1.</p>
                        </div>
                        <div>
                            <label class="label" for="b-max-attempts">Số lần làm tối đa</label>
                            <input id="b-max-attempts" type="number" min="1" max="20" class="input" wire:model="maxAttempts">
                            @error('maxAttempts') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            @if ($attemptsUsed > 0)
                                <p class="tnum mt-1.5 text-xs text-ink-faint dark:text-slate-500">
                                    Đã có {{ $attemptsUsed }} lượt trong lớp. Để 1 nếu học sinh chỉ làm một lần.
                                </p>
                            @else
                                <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Để 1 nếu học sinh chỉ làm một lần.</p>
                            @endif
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
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveMeta">
                            <span wire:loading.remove wire:target="saveMeta">Lưu thông tin</span>
                            <span wire:loading wire:target="saveMeta">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Câu hỏi trong {{ mb_strtolower($exam->type->label()) }}</h2>
                    <div class="flex items-center gap-2.5">
                        <span class="tnum text-sm text-ink-faint dark:text-slate-500">{{ $examQuestions->count() }} câu</span>
                        <button type="button" wire:click="openClusterForm({{ (int) $pickSectionId }})" class="btn btn-outline px-3 py-1.5 text-xs">+ Cụm Đúng/Sai</button>
                    </div>
                </div>

                @php
                    $groupedQuestions = $examQuestions->groupBy(fn ($eq) => $eq->exam_section_id !== null ? (string) $eq->exam_section_id : 'none');
                    $questionNo = 0;
                @endphp

                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($sections as $section)
                        @php $sectionItems = $groupedQuestions->get((string) $section->id, collect()); @endphp
                        <div wire:key="group-{{ $section->id }}">
                            <div class="flex flex-wrap items-center gap-2 bg-paper-2/60 px-5 py-2.5 dark:bg-white/[0.03]">
                                <p class="min-w-0 flex-1 text-sm font-semibold text-ink dark:text-white">{{ $section->title }}</p>
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $sectionItems->count() }} câu</span>
                                <button type="button" wire:click="openClusterForm({{ $section->id }})" class="btn btn-outline px-2.5 py-1.5 text-xs">+ Cụm Đúng/Sai</button>
                            </div>
                            <div class="px-5 pt-1">
                                <textarea rows="1" class="input text-xs" wire:model.blur="sectionInstructions.{{ $section->id }}"
                                    placeholder="Hướng dẫn làm phần, VD: Phần II — đọc đoạn và xác định đúng/sai các nhận định." aria-label="Hướng dẫn {{ $section->title }}"></textarea>
                            </div>
                            @if ($sectionItems->isEmpty())
                                <p class="empty px-5 pb-4">Chưa có câu hỏi trong phần này.</p>
                            @else
                                <div class="mt-1 divide-y divide-rule border-t border-rule dark:divide-night-700 dark:border-night-700">
                                    @foreach ($sectionItems as $examQuestion)
                                        @php $questionNo++; @endphp
                                        @include('livewire.teacher.assessment-builder-question', ['examQuestion' => $examQuestion, 'questionNo' => $questionNo])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        {{-- Không có phần nào — câu lẻ (nếu có) nằm ở khối "Không xếp phần" bên dưới. --}}
                    @endforelse

                    @php $orphanItems = $groupedQuestions->get('none', collect()); @endphp
                    @if ($orphanItems->isNotEmpty())
                        <div wire:key="group-none">
                            <div class="bg-paper-2/60 px-5 py-2.5 dark:bg-white/[0.03]">
                                <p class="text-sm font-semibold text-ink dark:text-white">Không xếp phần</p>
                            </div>
                            <div class="divide-y divide-rule dark:divide-night-700">
                                @foreach ($orphanItems as $examQuestion)
                                    @php $questionNo++; @endphp
                                    @include('livewire.teacher.assessment-builder-question', ['examQuestion' => $examQuestion, 'questionNo' => $questionNo])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($examQuestions->isEmpty())
                        <p class="empty">Chưa có câu hỏi. Chọn từ ngân hàng ở cột bên phải hoặc tạo cụm Đúng/Sai.</p>
                    @endif
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

    @if ($showClusterForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/60 p-0 sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeClusterForm()">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $clusterExamQuestionId !== null ? 'Sửa cụm Đúng/Sai' : 'Tạo cụm Đúng/Sai' }}</h2>
                        <p class="mt-0.5 text-xs text-ink-faint dark:text-slate-500">Một cụm = đoạn ngữ cảnh chung + 4 mệnh đề a)–d), chấm theo nấc BGD.</p>
                    </div>
                    <button type="button" wire:click="closeClusterForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="saveCluster" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="label" for="bc-section">Xếp vào phần</label>
                            <select id="bc-section" class="input" wire:model="clusterSectionId">
                                <option value="0">Không xếp phần</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="bc-points">Điểm</label>
                            <input id="bc-points" type="number" step="0.25" min="0.25" class="input tnum" wire:model="clusterPoints">
                            @error('clusterPoints') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="bc-content">Đoạn ngữ cảnh chung</label>
                        <textarea id="bc-content" rows="3" class="input" wire:model="clusterContent"
                            placeholder="Dán đoạn thông tin, bảng số liệu… dùng chung cho cả 4 mệnh đề (hỗ trợ Markdown)."></textarea>
                        @error('clusterContent') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <p class="label mb-2">4 mệnh đề — bấm nút để đánh dấu Đúng (2 đúng ¼ · 3 đúng ½ · 4 đúng trọn điểm)</p>
                        <div class="space-y-2">
                            @foreach ($clusterStatements as $index => $statement)
                                <div class="flex items-center gap-2" wire:key="bc-statement-{{ $index }}">
                                    <span class="tnum w-5 shrink-0 text-sm font-semibold text-ink-faint dark:text-slate-500">{{ chr(97 + $index) }})</span>
                                    <input type="text" class="input" wire:model="clusterStatements.{{ $index }}.content"
                                        placeholder="Mệnh đề {{ chr(97 + $index) }}" aria-label="Mệnh đề {{ chr(97 + $index) }}">
                                    <button type="button" wire:click="toggleClusterTruth({{ $index }})"
                                        class="shrink-0 rounded-[10px] border px-2.5 py-2 text-xs font-semibold transition-colors {{ $statement['is_correct'] ? 'border-success bg-success text-white' : 'border-rule-strong text-ink-faint hover:bg-paper-2 dark:border-night-700 dark:text-slate-400' }}"
                                        title="Đánh dấu mệnh đề này Đúng">
                                        {{ $statement['is_correct'] ? 'Đúng' : 'Sai' }}
                                    </button>
                                </div>
                                @error('clusterStatements.'.$index.'.content')
                                    <p class="ml-7 mt-1 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                        @error('clusterStatements') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label" for="bc-difficulty">Độ khó</label>
                            <select id="bc-difficulty" class="input" wire:model="clusterDifficulty">
                                <option value="easy">Dễ</option>
                                <option value="medium">Trung bình</option>
                                <option value="hard">Khó</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="bc-topic">Chủ đề</label>
                            <input id="bc-topic" type="text" class="input" wire:model="clusterTopic" placeholder="VD: Hàm số">
                        </div>
                    </div>

                    <div>
                        <label class="label" for="bc-explanation">Giải thích / đáp án gợi ý</label>
                        <textarea id="bc-explanation" rows="2" class="input" wire:model="clusterExplanation"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeClusterForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveCluster">
                            <span wire:loading.remove wire:target="saveCluster">Lưu cụm</span>
                            <span wire:loading wire:target="saveCluster">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
