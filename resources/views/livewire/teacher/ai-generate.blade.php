<div class="space-y-6">
    <x-teacher.tabs active="ai" />

    <div class="page-head">
        <div>
            <h1 class="page-title">AI Studio</h1>
            <p class="page-sub">Dùng AI soạn câu hỏi hoặc tài liệu cho môn <span class="font-medium" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>. Kết quả lưu vào đúng môn của bạn.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (! $configured)
        <div class="alert alert-warning">Chưa có nhà cung cấp AI nào được cấu hình. Báo quản trị viên thêm API key (OpenRouter hoặc Agnes AI) trong mục API key.</div>
    @else
        <p class="text-xs text-ink-faint dark:text-slate-500">
            Nhà cung cấp khả dụng:
            @foreach ($providers as $provider)
                <span class="chip chip-neutral ml-1">{{ $provider['label'] }} — {{ $provider['model'] }}</span>
            @endforeach
        </p>
    @endif

    @if ($aiError)
        <div class="alert alert-error">{{ $aiError }}</div>
    @endif

    <div class="panel">
        <div class="tabs px-5 pt-4">
            <button type="button" wire:click="$set('mode', 'questions')" class="tab {{ $mode === 'questions' ? 'tab-active' : '' }}">Tạo câu hỏi</button>
            <button type="button" wire:click="$set('mode', 'document')" class="tab {{ $mode === 'document' ? 'tab-active' : '' }}">Tạo tài liệu</button>
        </div>

        <div class="space-y-4 p-5">
            <div>
                <label class="label" for="ai-topic">Chủ đề hoặc yêu cầu</label>
                <textarea id="ai-topic" rows="2" class="input" wire:model="topic" placeholder="VD: Bất đẳng thức Cauchy, chuyên đề HSG lớp 10"></textarea>
                @error('topic') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="ai-instructions">Yêu cầu bổ sung (không bắt buộc)</label>
                <input id="ai-instructions" type="text" class="input" wire:model="instructions" placeholder="VD: tập trung dạng tìm min, max">
            </div>

            @if ($mode === 'questions')
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="label" for="ai-count">Số câu</label>
                        <input id="ai-count" type="number" min="1" max="15" class="input tnum" wire:model="count">
                        @error('count') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="ai-type">Dạng</label>
                        <select id="ai-type" class="input" wire:model="questionType">
                            <option value="mixed">Trộn lẫn</option>
                            <option value="multiple_choice">Trắc nghiệm</option>
                            <option value="fill_blank">Điền khuyết</option>
                            <option value="essay">Tự luận</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ai-difficulty">Độ khó</label>
                        <select id="ai-difficulty" class="input" wire:model="difficulty">
                            <option value="easy">Dễ</option>
                            <option value="medium">Trung bình</option>
                            <option value="hard">Khó</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ai-points">Điểm mặc định</label>
                        <input id="ai-points" type="number" step="0.25" min="0.25" class="input tnum" wire:model="points">
                    </div>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="ai-doc-title">Tiêu đề tài liệu</label>
                        <input id="ai-doc-title" type="text" class="input" wire:model="documentTitle" placeholder="Để trống sẽ lấy theo chủ đề">
                    </div>
                    <div>
                        <label class="label" for="ai-doc-category">Danh mục</label>
                        <input id="ai-doc-category" type="text" class="input" wire:model="documentCategory">
                    </div>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                    <input type="checkbox" wire:model="documentIsPublic" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                    Công khai cho học sinh trong đội
                </label>
            @endif

            <div class="flex items-center justify-between gap-3 pt-1">
                <p class="tnum text-xs text-ink-faint dark:text-slate-500">
                    @if ($usedTokens !== null)
                        Đã dùng {{ $usedTokens }} token — {{ $providerLabel }} — {{ $modelLabel }}
                    @endif
                </p>

                @if ($mode === 'questions')
                    <button type="button" wire:click="generateQuestions" class="btn btn-primary" wire:loading.attr="disabled" @disabled(! $configured)>
                        <x-icon name="sparkles" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="generateQuestions">Tạo câu hỏi</span>
                        <span wire:loading wire:target="generateQuestions">AI đang soạn…</span>
                    </button>
                @else
                    <button type="button" wire:click="generateDocument" class="btn btn-primary" wire:loading.attr="disabled" @disabled(! $configured)>
                        <x-icon name="sparkles" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="generateDocument">Tạo tài liệu</span>
                        <span wire:loading wire:target="generateDocument">AI đang viết…</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if ($draft !== [])
        <div class="panel">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Xem trước {{ count($draft) }} câu hỏi</h2>
                <div class="flex gap-2">
                    <button type="button" wire:click="clearDraft" class="btn btn-ghost px-3 py-1.5 text-xs">Bỏ</button>
                    <button type="button" wire:click="importSelected" class="btn btn-primary px-3.5 py-1.5 text-xs">Lưu {{ count($selected) }} câu đã chọn</button>
                </div>
            </div>

            <div class="divide-y divide-rule dark:divide-night-700">
                @foreach ($draft as $index => $item)
                    <div class="flex items-start gap-3 px-5 py-4" wire:key="draft-{{ $index }}">
                        <input type="checkbox" value="{{ $index }}" wire:model="selected"
                            class="mt-1 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="chip chip-neutral">{{ $item['type'] }}</span>
                                <span class="chip chip-neutral">{{ $item['difficulty'] }}</span>
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ (float) $item['points'] }} điểm</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line leading-relaxed text-ink dark:text-slate-100">{{ $item['content'] }}</p>

                            @if (! empty($item['options']))
                                <ul class="mt-2 grid gap-1.5 sm:grid-cols-2">
                                    @foreach ($item['options'] as $option)
                                        <li class="text-sm {{ ! empty($option['is_correct']) ? 'font-medium text-success' : 'text-ink-soft dark:text-slate-400' }}">{{ $option['content'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            @elseif (! empty($item['answer']))
                                <p class="mt-2 text-sm text-ink-soft dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $item['answer'] }}</p>
                            @endif

                            @if (! empty($item['explanation']))
                                <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Giải thích: {{ $item['explanation'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
