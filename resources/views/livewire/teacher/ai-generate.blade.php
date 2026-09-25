<div class="space-y-6">
    <x-teacher.tabs active="ai" />

    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">AI Studio</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Dùng AI tạo câu hỏi hoặc tài liệu cho môn {{ $subject?->name }}. Kết quả được lưu vào đúng môn của bạn.
        </p>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (! $configured)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Chưa có nhà cung cấp AI nào được cấu hình. Vui lòng báo quản trị viên thêm API key (OpenRouter/Agnes AI) trong mục API key.
        </div>
    @else
        <p class="text-xs text-slate-400">
            Nhà cung cấp khả dụng:
            @foreach ($providers as $provider)
                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $provider['label'] }} · {{ $provider['model'] }}</span>
            @endforeach
        </p>
    @endif

    @if ($aiError)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
            {{ $aiError }}
        </div>
    @endif

    <div class="card space-y-4">
        <div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">
            <button type="button" wire:click="$set('mode', 'questions')"
                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium {{ $mode === 'questions' ? 'bg-white text-brand-700 shadow-sm dark:bg-night-700 dark:text-brand-300' : 'text-slate-500 dark:text-slate-400' }}">
                Tạo câu hỏi
            </button>
            <button type="button" wire:click="$set('mode', 'document')"
                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium {{ $mode === 'document' ? 'bg-white text-brand-700 shadow-sm dark:bg-night-700 dark:text-brand-300' : 'text-slate-500 dark:text-slate-400' }}">
                Tạo tài liệu
            </button>
        </div>

        <div>
            <label class="label" for="ai-topic">Chủ đề / yêu cầu</label>
            <textarea id="ai-topic" rows="2" class="input" wire:model="topic"
                placeholder="VD: Bất đẳng thức Cauchy, chuyên đề HSG lớp 10"></textarea>
            @error('topic') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="ai-instructions">Yêu cầu bổ sung (không bắt buộc)</label>
            <input id="ai-instructions" type="text" class="input" wire:model="instructions" placeholder="VD: tập trung dạng tìm min/max">
        </div>

        @if ($mode === 'questions')
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label class="label" for="ai-count">Số câu</label>
                    <input id="ai-count" type="number" min="1" max="15" class="input" wire:model="count">
                    @error('count') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
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
                    <input id="ai-points" type="number" step="0.25" min="0.25" class="input" wire:model="points">
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="ai-doc-title">Tiêu đề tài liệu (để trống lấy theo chủ đề)</label>
                    <input id="ai-doc-title" type="text" class="input" wire:model="documentTitle">
                </div>
                <div>
                    <label class="label" for="ai-doc-category">Danh mục</label>
                    <input id="ai-doc-category" type="text" class="input" wire:model="documentCategory">
                </div>
            </div>
            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model="documentIsPublic" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                Công khai cho học sinh trong đội
            </label>
        @endif

        <div class="flex justify-end">
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

        @if ($usedTokens !== null)
            <p class="text-xs text-slate-400">Đã dùng {{ $usedTokens }} token · {{ $providerLabel }} · {{ $modelLabel }}</p>
        @endif
    </div>

    @if ($draft !== [])
        <div class="card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Xem trước {{ count($draft) }} câu hỏi</h2>
                <div class="flex gap-2">
                    <button type="button" wire:click="clearDraft" class="btn btn-ghost px-3 py-1.5 text-xs">Bỏ</button>
                    <button type="button" wire:click="importSelected" class="btn btn-primary px-3 py-1.5 text-xs">
                        Lưu {{ count($selected) }} câu đã chọn
                    </button>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($draft as $index => $item)
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-white/10" wire:key="draft-{{ $index }}">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" value="{{ $index }}" wire:model="selected"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-slate-400">{{ $item['type'] }} · {{ $item['difficulty'] }} · {{ (float) $item['points'] }} điểm</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-800 dark:text-slate-100">{{ $item['content'] }}</p>

                                @if (! empty($item['options']))
                                    <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                                        @foreach ($item['options'] as $option)
                                            <li class="text-sm {{ ! empty($option['is_correct']) ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                                                {{ $option['content'] ?? '' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif (! empty($item['answer']))
                                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><span class="font-medium">Đáp án:</span> {{ $item['answer'] }}</p>
                                @endif

                                @if (! empty($item['explanation']))
                                    <p class="mt-1 text-xs text-slate-400">Giải thích: {{ $item['explanation'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
