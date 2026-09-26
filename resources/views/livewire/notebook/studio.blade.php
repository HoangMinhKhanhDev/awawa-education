<div class="@container flex h-full min-h-0 flex-col"
    @if ($isGenerating)
        wire:poll.4s="poll"
    @endif
    >
    {{-- Đầu màn: quay lại danh sách định dạng khi đang mở một định dạng --}}
    <div class="flex h-12 shrink-0 items-center justify-between gap-2 px-4">
        @if ($view === 'type' && $activeTypeEnum)
            <button type="button" wire:click="backToBrowse"
                class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-ink transition-colors hover:text-brand-700 dark:text-white dark:hover:text-brand-300">
                <x-icon name="arrow-left" class="h-4 w-4 shrink-0" />
                <span class="truncate">Studio</span>
                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-faint" />
                <span class="truncate">{{ $activeTypeEnum->label() }}</span>
            </button>
        @else
            <h2 class="text-sm font-semibold text-ink dark:text-white">Studio</h2>
        @endif

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('studio.ai.activity') }}" class="text-xs font-medium text-ink-faint hover:text-brand-700 dark:text-slate-500 dark:hover:text-brand-300">Hoạt động AI</a>
            <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $typeCounts->get('all', 0) }}</span>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        @if (session('notebook_status'))
            <div class="alert alert-success mx-3 mt-3">{{ session('notebook_status') }}</div>
        @endif

        @if ($notice)
            <div class="alert alert-success mx-3 mt-3 flex items-start gap-2">
                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ $notice }}</span>
            </div>
        @endif

        @if ($error)
            <div class="alert alert-error mx-3 mt-3">
                <p>{{ $error }}</p>
                @if ($rateLimited)
                    <p class="mt-1 text-xs">Yêu cầu của bạn đã được giữ nguyên. Hãy thử lại sau, hoặc nhờ quản trị viên thêm một nhà cung cấp AI dự phòng ở trang API key.</p>
                @endif
            </div>
        @endif

        @if ($types->isEmpty())
            <p class="empty">Môn này chưa bật tính năng tạo nội dung. Liên hệ quản trị viên để mở ở trang Cấu hình AI.</p>
        @elseif ($view === 'type' && $activeTypeEnum)

            {{-- Màn định dạng: bên trái panel tuỳ chỉnh, bên phải kết quả (khi cột đủ rộng) --}}
            <div class="grid grid-cols-1 gap-3 p-3 @2xl:grid-cols-[minmax(0,21rem)_minmax(0,1fr)] @2xl:items-start">
                <section class="rounded-[14px] border border-rule p-3 dark:border-night-700">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] {{ $activeTypeEnum->tone() }}">
                            <x-icon :name="$activeTypeEnum->icon()" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink dark:text-white">Tuỳ chỉnh</span>
                            <span class="block text-[11px] text-ink-faint dark:text-slate-500">{{ $activeTypeEnum->description() }}</span>
                        </span>
                    </div>

                    @unless ($hasSources)
                        <div class="alert alert-warning mb-3">Chưa bật nguồn nào. Thêm và bật nguồn ở cột “Nguồn” để kết quả bám tài liệu.</div>
                    @endunless

                    <form wire:submit="generate" class="space-y-3">
                        <div>
                            <label class="label" for="st-instruction">
                                Gợi ý cho AI
                                <span class="font-normal text-ink-faint dark:text-slate-500">(không bắt buộc)</span>
                            </label>
                            <textarea id="st-instruction" rows="2" class="input" wire:model="instruction"
                                placeholder="Để trống để AI tự soạn từ các nguồn đang bật. VD: tập trung chuyên đề bất đẳng thức cho đội tuyển."></textarea>
                            @error('instruction') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-[11px] text-ink-faint dark:text-slate-500">
                                AI bám theo các nguồn đang bật ở cột “Nguồn”. Bạn có thể đóng tab này, nội dung vẫn được soạn nền.
                            </p>
                        </div>

                        @if ($activeType === \App\Enums\ArtifactType::Questions->value)
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label" for="st-count">Số câu</label>
                                    <input id="st-count" type="number" min="1" max="20" class="input tnum" wire:model="count">
                                </div>
                                <div>
                                    <label class="label" for="st-qt">Dạng</label>
                                    <select id="st-qt" class="input" wire:model="questionType">
                                        <option value="mixed">Trộn lẫn</option>
                                        <option value="multiple_choice">Trắc nghiệm</option>
                                        <option value="fill_blank">Điền khuyết</option>
                                        <option value="essay">Tự luận</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="st-diff">Độ khó</label>
                                    <select id="st-diff" class="input" wire:model="difficulty">
                                        <option value="easy">Dễ</option>
                                        <option value="medium">Trung bình</option>
                                        <option value="hard">Khó</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="st-points">Điểm/câu</label>
                                    <input id="st-points" type="number" step="0.25" min="0.25" class="input tnum" wire:model="points">
                                </div>
                            </div>
                        @elseif ($activeType === \App\Enums\ArtifactType::Exam->value)
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label" for="st-exam-sections">Số phần</label>
                                    <input id="st-exam-sections" type="number" min="1" max="6" class="input tnum" wire:model="examSections">
                                </div>
                                <div>
                                    <label class="label" for="st-exam-count">Câu mỗi phần</label>
                                    <input id="st-exam-count" type="number" min="1" max="30" class="input tnum" wire:model="examQuestionsPerSection">
                                </div>
                                <div>
                                    <label class="label" for="st-exam-total">Tổng điểm đề</label>
                                    <input id="st-exam-total" type="number" step="0.5" min="1" max="100" class="input tnum" wire:model="examTotalPoints">
                                </div>
                                <div>
                                    <label class="label" for="st-exam-duration">Thời gian (phút)</label>
                                    <input id="st-exam-duration" type="number" min="1" max="600" class="input tnum" wire:model="examDurationMinutes">
                                </div>
                                <div class="col-span-2">
                                    <label class="label" for="st-exam-qt">Tỉ lệ câu hỏi</label>
                                    <select id="st-exam-qt" class="input" wire:model="questionType">
                                        <option value="mixed">Trộn lẫn trắc nghiệm và tự luận</option>
                                        <option value="multiple_choice">Toàn bộ là trắc nghiệm</option>
                                        <option value="essay">Toàn bộ là tự luận</option>
                                        <option value="fill_blank">Toàn bộ là điền khuyết</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="st-exam-diff">Độ khó chung</label>
                                    <select id="st-exam-diff" class="input" wire:model="difficulty">
                                        <option value="easy">Dễ</option>
                                        <option value="medium">Trung bình</option>
                                        <option value="hard">Khó</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <p class="tnum pb-2 text-[11px] leading-relaxed text-ink-faint dark:text-slate-500">
                                        {{ \App\Services\Notebook\ArtifactGenerator::examPointsPerQuestion((int) $examSections, (int) $examQuestionsPerSection, (float) $examTotalPoints) }} điểm/câu
                                        · {{ (int) $examSections * (int) $examQuestionsPerSection }} câu
                                    </p>
                                </div>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="generate">
                            <span wire:loading.remove wire:target="generate">
                                <x-icon name="sparkles" class="h-4 w-4" />
                                Tạo {{ \Illuminate\Support\Str::lower($activeTypeEnum->label()) }}
                            </span>
                            <span wire:loading wire:target="generate">Đang bắt đầu…</span>
                        </button>
                    </form>
                </section>

                <section>
                    <h3 class="mb-2 text-xs font-semibold text-ink dark:text-slate-200">
                        Đã tạo
                        <span class="tnum font-normal text-ink-faint dark:text-slate-500">({{ $artifacts->count() }})</span>
                    </h3>

                    <div class="grid grid-cols-1 gap-2 @xl:grid-cols-2">
                        @forelse ($artifacts as $artifact)
                            <article class="flex flex-col rounded-[12px] border p-3 transition-colors dark:border-night-700
                                {{ $artifact->isGenerating() ? 'border-brand-300 bg-brand-50/50 dark:border-brand-500/40 dark:bg-brand-500/5' : ($artifact->isFailed() ? 'border-signal/40 bg-signal-soft/40 dark:border-red-500/30' : 'border-rule hover:border-rule-strong dark:hover:border-slate-600') }}"
                                wire:key="artifact-card-{{ $artifact->id }}">

                                @if ($artifact->isGenerating())
                                    <div class="flex flex-1 items-start gap-2">
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-brand-600 dark:text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 text-sm text-ink dark:text-slate-100">{{ $artifact->title }}</p>
                                            <p class="mt-1 text-[11px] text-ink-faint dark:text-slate-500">AI đang soạn nền. Bạn có thể chuyển sang màn khác.</p>
                                        </div>
                                    </div>
                                @elseif ($artifact->isFailed())
                                    <div class="flex flex-1 items-start gap-2">
                                        <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0 text-signal dark:text-red-400" />
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 text-sm text-ink dark:text-slate-100">{{ $artifact->title }}</p>
                                            <p class="mt-1 text-[11px] text-signal dark:text-red-400">{{ $artifact->failedReason() }}</p>
                                        </div>
                                    </div>
                                @else
                                    <button type="button" wire:click="openPreview({{ $artifact->id }})" class="flex-1 text-left">
                                        <span class="line-clamp-2 block text-sm text-ink dark:text-slate-100">{{ $artifact->title }}</span>
                                        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="chip {{ $artifact->isPublished() ? 'chip-success' : 'chip-warning' }}">{{ $artifact->isPublished() ? 'Đã xuất bản' : 'Nháp' }}</span>
                                            <span class="text-[11px] text-ink-faint dark:text-slate-500">{{ $artifact->updated_at->diffForHumans() }}</span>
                                        </span>
                                    </button>
                                @endif

                                @unless ($artifact->isGenerating())
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        @if ($artifact->isPublished())
                                            <button type="button" wire:click="openPreview({{ $artifact->id }})" class="btn btn-outline px-2 py-1 text-[11px]">Xem</button>
                                            <a href="{{ route('studio.ai.artifacts.export', ['artifact' => $artifact->id, 'format' => 'docx']) }}"
                                                class="btn btn-ghost px-2 py-1 text-[11px]">DOCX</a>
                                        @else
                                            @if ($artifact->isFailed())
                                                <button type="button" wire:click="regenerate({{ $artifact->id }})" wire:loading.attr="disabled" wire:target="regenerate({{ $artifact->id }})"
                                                    class="btn btn-primary px-2 py-1 text-[11px]">Thử lại</button>
                                            @else
                                                <button type="button" wire:click="openPreview({{ $artifact->id }})" class="btn btn-outline px-2 py-1 text-[11px]">Xem</button>
                                                <button type="button" wire:click="regenerate({{ $artifact->id }})" wire:loading.attr="disabled" wire:target="regenerate({{ $artifact->id }})"
                                                    wire:confirm="Tạo lại nội dung này bằng AI? Bản nháp hiện tại sẽ bị thay thế."
                                                    class="btn btn-ghost px-2 py-1 text-[11px]">Tạo lại</button>
                                                <button type="button" wire:click="publish({{ $artifact->id }})" wire:loading.attr="disabled" wire:target="publish({{ $artifact->id }})"
                                                    class="btn btn-primary px-2 py-1 text-[11px]">Xuất bản</button>
                                            @endif
                                            <button type="button" wire:click="delete({{ $artifact->id }})" wire:confirm="Xóa nội dung này?"
                                                class="ml-auto rounded-[10px] p-1.5 text-ink-faint transition-colors hover:bg-signal-soft hover:text-signal dark:hover:bg-red-500/10" title="Xóa">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                @endunless
                            </article>
                        @empty
                            <p class="empty col-span-full">
                                Chưa có {{ \Illuminate\Support\Str::lower($activeTypeEnum->label()) }} nào. Điền yêu cầu ở ô bên trái rồi bấm “Tạo”.
                            </p>
                        @endforelse
                    </div>

                    @if ($artifacts->count() >= 30)
                        <p class="px-1 py-3 text-center text-[11px] text-ink-faint dark:text-slate-500">Chỉ hiện 30 nội dung gần nhất.</p>
                    @endif
                </section>
            </div>
        @else

            {{-- Màn chọn định dạng --}}
            <div class="p-3">
                <h3 class="mb-2 text-xs font-semibold text-ink dark:text-slate-200">Tạo nội dung mới</h3>

                <div class="grid grid-cols-1 gap-2 @sm:grid-cols-2 @3xl:grid-cols-3">
                    @foreach ($types as $type)
                        <button type="button" wire:click="selectType('{{ $type->value }}')" title="{{ $type->description() }}"
                            class="group flex items-start gap-3 rounded-[14px] border border-rule p-3 text-left transition-colors hover:border-brand-300 hover:bg-paper-2 dark:border-night-700 dark:hover:border-brand-500/40 dark:hover:bg-white/[0.03]"
                            wire:key="format-{{ $type->value }}">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] {{ $type->tone() }}">
                                <x-icon :name="$type->icon()" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-sm font-semibold text-ink dark:text-white">{{ $type->label() }}</span>
                                    @if ($typeCounts->get($type->value, 0) > 0)
                                        <span class="tnum chip chip-neutral">{{ $typeCounts->get($type->value, 0) }}</span>
                                    @endif
                                </span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-ink-soft dark:text-slate-400">{{ $type->description() }}</span>
                            </span>
                            <x-icon name="chevron-right" class="mt-1 h-4 w-4 shrink-0 text-ink-faint transition-colors group-hover:text-brand-600 dark:group-hover:text-brand-400" />
                        </button>
                    @endforeach
                </div>

                <h3 class="mb-1 mt-5 text-xs font-semibold text-ink dark:text-slate-200">Nội dung gần đây</h3>

                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($artifacts as $artifact)
                        @php $rowType = \App\Enums\ArtifactType::tryFrom($artifact->type); @endphp
                        <div class="group flex items-start gap-2 py-2.5" wire:key="artifact-row-{{ $artifact->id }}">
                            @if ($artifact->isGenerating())
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-1.5 text-sm text-ink dark:text-slate-100">
                                        <svg class="h-3.5 w-3.5 shrink-0 animate-spin text-brand-600 dark:text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                        <span class="truncate">{{ $artifact->title }}</span>
                                    </p>
                                    <p class="mt-1 text-[11px] text-ink-faint dark:text-slate-500">Đang soạn nền, có thể chuyển sang màn khác.</p>
                                </div>
                            @else
                                <button type="button" wire:click="openPreview({{ $artifact->id }})" class="min-w-0 flex-1 text-left">
                                    <p class="line-clamp-2 text-sm text-ink dark:text-slate-100">{{ $artifact->title }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @if ($rowType)
                                            <span class="chip chip-neutral">{{ $rowType->label() }}</span>
                                        @endif
                                        @if ($artifact->isFailed())
                                            <span class="chip chip-warning">Soạn lỗi</span>
                                        @else
                                            <span class="chip {{ $artifact->isPublished() ? 'chip-success' : 'chip-warning' }}">{{ $artifact->isPublished() ? 'Đã xuất bản' : 'Nháp' }}</span>
                                        @endif
                                        <span class="text-[11px] text-ink-faint dark:text-slate-500">{{ $artifact->updated_at->diffForHumans() }}</span>
                                    </p>
                                </button>

                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($artifact->isPublished())
                                        <a href="{{ route('studio.ai.artifacts.export', ['artifact' => $artifact->id, 'format' => 'docx']) }}"
                                            class="btn btn-ghost px-2 py-1 text-[11px]">DOCX</a>
                                    @elseif ($artifact->isFailed())
                                        <button type="button" wire:click="regenerate({{ $artifact->id }})" wire:loading.attr="disabled" wire:target="regenerate({{ $artifact->id }})"
                                            class="btn btn-outline px-2 py-1 text-[11px]">Thử lại</button>
                                        <button type="button" wire:click="delete({{ $artifact->id }})" wire:confirm="Xóa nội dung này?"
                                            class="rounded-[10px] p-1.5 text-ink-faint transition-colors hover:bg-signal-soft hover:text-signal dark:hover:bg-red-500/10" title="Xóa">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    @else
                                        <button type="button" wire:click="publish({{ $artifact->id }})" wire:loading.attr="disabled" wire:target="publish({{ $artifact->id }})"
                                            class="btn btn-outline px-2 py-1 text-[11px]">Xuất bản</button>
                                        <button type="button" wire:click="delete({{ $artifact->id }})" wire:confirm="Xóa nội dung này?"
                                            class="rounded-[10px] p-1.5 text-ink-faint transition-colors hover:bg-signal-soft hover:text-signal dark:hover:bg-red-500/10" title="Xóa">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="empty">Chưa có nội dung nào. Chọn một định dạng ở trên để bắt đầu.</p>
                    @endforelse
                </div>

                @if ($artifacts->count() >= 30)
                    <p class="px-1 py-3 text-center text-[11px] text-ink-faint dark:text-slate-500">Chỉ hiện 30 nội dung gần nhất.</p>
                @endif
            </div>
        @endif
    </div>

    {{-- Modal xem trước --}}
    @if ($preview)
        @php $previewType = \App\Enums\ArtifactType::from($preview->type); @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="notebook-artifact-print flex max-h-[90vh] w-full {{ $previewType === \App\Enums\ArtifactType::Exam ? 'max-w-3xl' : 'max-w-2xl' }} flex-col rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">{{ $preview->title }}</h2>
                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="chip chip-neutral">{{ $previewType->label() }}</span>
                            @if ($preview->isGenerating())
                                <span class="chip chip-warning">Đang soạn</span>
                            @elseif ($preview->isFailed())
                                <span class="chip chip-warning">Soạn lỗi</span>
                            @else
                                <span class="chip {{ $preview->isPublished() ? 'chip-success' : 'chip-warning' }}">{{ $preview->isPublished() ? 'Đã xuất bản' : 'Nháp' }}</span>
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closePreview" class="notebook-print-hide rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                @if ($preview->isGenerating())
                    <div class="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
                        <svg class="h-6 w-6 animate-spin text-brand-600 dark:text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <p class="text-sm text-ink dark:text-slate-200">AI đang soạn nội dung…</p>
                        <p class="text-xs text-ink-faint dark:text-slate-500">Bạn có thể đóng cửa sổ này hoặc chuyển sang màn khác, quá trình vẫn chạy nền.</p>
                        <button type="button" wire:click="closePreview" class="btn btn-outline text-xs">Đóng</button>
                    </div>
                @elseif ($preview->isFailed())
                    <div class="alert alert-error mb-3">
                        <p>{{ $preview->failedReason() }}</p>
                        <button type="button" wire:click="regenerate({{ $preview->id }})" class="btn btn-outline mt-2 text-xs">Thử lại</button>
                    </div>
                @elseif ($editingPreview)
                    <form wire:submit="saveDraft" class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
                        @if ($error || $errors->any())
                            <div class="alert alert-error">{{ $error ?: $errors->first() }}</div>
                        @endif
                        <div>
                            <label class="label" for="st-draft-title">Tiêu đề</label>
                            <input id="st-draft-title" type="text" class="input" wire:model="draftTitle">
                            @error('draftTitle') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        @if ($previewType === \App\Enums\ArtifactType::Questions)
                            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                                @foreach ($draftPayload['items'] ?? [] as $index => $item)
                                    <section class="rounded-[12px] border border-rule p-3 dark:border-night-700" wire:key="edit-question-{{ $index }}">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <span class="text-xs font-semibold text-ink dark:text-slate-200">Câu {{ $index + 1 }}</span>
                                            <label class="flex items-center gap-1.5 text-xs text-ink-soft dark:text-slate-400">
                                                <input type="checkbox" wire:model="draftPayload.items.{{ $index }}.included" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500">
                                                Đưa vào ngân hàng
                                            </label>
                                        </div>
                                        <textarea rows="2" class="input" wire:model="draftPayload.items.{{ $index }}.content" aria-label="Nội dung câu {{ $index + 1 }}"></textarea>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            <select class="input py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.type" aria-label="Dạng câu hỏi">
                                                <option value="multiple_choice">Trắc nghiệm</option><option value="fill_blank">Điền khuyết</option><option value="essay">Tự luận</option>
                                            </select>
                                            <input type="number" min="0" max="100" step="0.25" class="input py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.points" aria-label="Điểm câu hỏi">
                                        </div>
                                        @foreach ($item['options'] ?? [] as $optionIndex => $option)
                                            <div class="mt-2 flex items-center gap-2" wire:key="edit-option-{{ $index }}-{{ $optionIndex }}">
                                                <input type="checkbox" wire:model="draftPayload.items.{{ $index }}.options.{{ $optionIndex }}.is_correct" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500" aria-label="Đáp án đúng">
                                                <input type="text" class="input py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.options.{{ $optionIndex }}.content" aria-label="Lựa chọn {{ $optionIndex + 1 }}">
                                            </div>
                                        @endforeach
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            <input type="text" class="input py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.answer" placeholder="Đáp án / lời giải ngắn">
                                            <select class="input py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.difficulty" aria-label="Độ khó">
                                                <option value="easy">Dễ</option>
                                                <option value="medium">Trung bình</option>
                                                <option value="hard">Khó</option>
                                            </select>
                                        </div>
                                        <textarea rows="2" class="input mt-2 py-2 text-xs" wire:model="draftPayload.items.{{ $index }}.explanation" placeholder="Giải thích"></textarea>
                                    </section>
                                @endforeach
                            </div>
                        @elseif ($previewType === \App\Enums\ArtifactType::Exam)
                            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                                <label class="label" for="draft-exam-description">Mô tả đề</label>
                                <textarea id="draft-exam-description" rows="2" class="input" wire:model="draftPayload.description"></textarea>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="label" for="draft-exam-duration">Thời gian (phút)</label>
                                        <input id="draft-exam-duration" type="number" min="1" max="600" class="input tnum" wire:model="draftPayload.settings.duration_minutes">
                                    </div>
                                    <div>
                                        <label class="label" for="draft-exam-total">Tổng điểm đề</label>
                                        <input id="draft-exam-total" type="number" step="0.5" min="1" max="100" class="input tnum" wire:model="draftPayload.settings.total_points">
                                    </div>
                                    <div class="flex flex-col justify-end gap-1 pb-1 text-xs text-ink-soft dark:text-slate-300">
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" wire:model="draftPayload.settings.shuffle_questions" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500">
                                            Trộn câu
                                        </label>
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" wire:model="draftPayload.settings.shuffle_options" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500">
                                            Trộn lựa chọn
                                        </label>
                                    </div>
                                </div>
                                @foreach ($draftPayload['sections'] ?? [] as $sectionIndex => $section)
                                    <section class="rounded-[12px] border border-rule p-3 dark:border-night-700" wire:key="edit-section-{{ $sectionIndex }}">
                                        <label class="label">Tên phần</label>
                                        <input type="text" class="input" wire:model="draftPayload.sections.{{ $sectionIndex }}.title">
                                        <textarea rows="2" class="input mt-2" wire:model="draftPayload.sections.{{ $sectionIndex }}.instructions" placeholder="Hướng dẫn làm phần"></textarea>
                                        @foreach ($section['questions'] ?? [] as $questionIndex => $question)
                                            <div class="mt-3 border-t border-rule pt-3 dark:border-night-700" wire:key="edit-exam-question-{{ $sectionIndex }}-{{ $questionIndex }}">
                                                <label class="mb-2 flex items-center justify-between gap-2 text-xs text-ink-soft dark:text-slate-400">
                                                    <span>Câu {{ $questionIndex + 1 }} · {{ $question['points'] ?? 1 }} điểm</span>
                                                    <span class="flex items-center gap-1.5"><input type="checkbox" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.included" class="h-4 w-4 rounded border-rule-strong text-brand-600"> Giữ câu</span>
                                                </label>
                                                <textarea rows="2" class="input" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.content"></textarea>
                                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                                    <select class="input py-2 text-xs" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.type" aria-label="Dạng câu hỏi">
                                                        <option value="multiple_choice">Trắc nghiệm</option>
                                                        <option value="fill_blank">Điền khuyết</option>
                                                        <option value="essay">Tự luận</option>
                                                    </select>
                                                    <input type="number" min="0" max="100" step="0.25" class="input py-2 text-xs tnum" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.points" aria-label="Điểm câu hỏi">
                                                    <select class="input py-2 text-xs" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.difficulty" aria-label="Độ khó">
                                                        <option value="easy">Dễ</option>
                                                        <option value="medium">Trung bình</option>
                                                        <option value="hard">Khó</option>
                                                    </select>
                                                </div>
                                                @if (($question['type'] ?? '') === 'multiple_choice')
                                                    <div class="mt-2 space-y-1.5">
                                                        @foreach ($question['options'] ?? [] as $optionIndex => $option)
                                                            <div class="flex items-center gap-2" wire:key="edit-exam-option-{{ $sectionIndex }}-{{ $questionIndex }}-{{ $optionIndex }}">
                                                                <input type="checkbox" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.is_correct"
                                                                    class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500" aria-label="Đáp án đúng">
                                                                <input type="text" class="input py-2 text-xs" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.options.{{ $optionIndex }}.content"
                                                                    placeholder="Lựa chọn {{ $optionIndex + 1 }}">
                                                                <button type="button" wire:click="removeExamOption({{ $sectionIndex }}, {{ $questionIndex }}, {{ $optionIndex }})"
                                                                    class="shrink-0 rounded-[10px] p-1.5 text-ink-faint transition-colors hover:bg-signal-soft hover:text-signal dark:hover:bg-red-500/10" title="Xoá lựa chọn" aria-label="Xoá lựa chọn {{ $optionIndex + 1 }}">
                                                                    <x-icon name="x" class="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                        <button type="button" wire:click="addExamOption({{ $sectionIndex }}, {{ $questionIndex }})"
                                                            class="btn btn-ghost px-2 py-1 text-[11px]">+ Thêm lựa chọn</button>
                                                    </div>
                                                @endif
                                                <input type="text" class="input mt-2 py-2 text-xs" wire:model="draftPayload.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.answer" placeholder="Đáp án / lời giải ngắn">
                                            </div>
                                        @endforeach
                                    </section>
                                @endforeach
                            </div>
                        @elseif ($previewType === \App\Enums\ArtifactType::Flashcards)
                            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                                @foreach ($draftPayload['cards'] ?? [] as $index => $card)
                                    <div class="grid gap-2 rounded-[12px] border border-rule p-3 sm:grid-cols-[1fr_1fr_auto] dark:border-night-700" wire:key="edit-card-{{ $index }}">
                                        <textarea rows="3" class="input" wire:model="draftPayload.cards.{{ $index }}.front" placeholder="Mặt trước"></textarea>
                                        <textarea rows="3" class="input" wire:model="draftPayload.cards.{{ $index }}.back" placeholder="Mặt sau"></textarea>
                                        <button type="button" wire:click="removeDraftCard({{ $index }})" class="btn btn-ghost px-2 text-signal" aria-label="Xóa thẻ">Xóa</button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addDraftCard" class="btn btn-outline w-full text-xs">Thêm thẻ</button>
                            </div>
                        @elseif ($previewType === \App\Enums\ArtifactType::MindMap)
                            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                                @foreach ($draftPayload['nodes'] ?? [] as $index => $node)
                                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr]" wire:key="edit-node-{{ $index }}">
                                        <input type="text" class="input" wire:model="draftPayload.nodes.{{ $index }}.label" placeholder="Nhãn node">
                                        <input type="text" class="input" wire:model="draftPayload.nodes.{{ $index }}.parent" placeholder="ID node cha (để trống nếu gốc)">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex min-h-0 flex-1 flex-col">
                                <label class="label" for="st-draft-text">Nội dung Markdown</label>
                                <textarea id="st-draft-text" class="input min-h-48 flex-1 resize-y" wire:model="draftText"></textarea>
                                @error('draftText') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="notebook-print-hide flex flex-wrap justify-end gap-2 border-t border-rule pt-3 dark:border-night-700">
                            <button type="button" wire:click="$set('editingPreview', false)" class="btn btn-ghost">Hủy</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveDraft">
                                <span wire:loading.remove wire:target="saveDraft">Lưu bản nháp</span>
                                <span wire:loading wire:target="saveDraft">Đang lưu…</span>
                            </button>
                        </div>
                    </form>
                @else

                <div class="flex-1 space-y-3 overflow-y-auto pr-1">
                    @if ($previewType === \App\Enums\ArtifactType::Questions)
                        @foreach ($preview->payload['items'] ?? [] as $index => $item)
                            <div class="rounded-[10px] border border-rule p-3 dark:border-night-700">
                                <p class="text-sm text-ink dark:text-slate-100">{{ $index + 1 }}. {{ $item['content'] }}</p>
                                @foreach ($item['options'] ?? [] as $option)
                                    <p class="mt-1 text-sm {{ ! empty($option['is_correct']) ? 'font-medium text-success' : 'text-ink-soft dark:text-slate-400' }}">— {{ $option['content'] }}</p>
                                @endforeach
                                @if (! empty($item['answer']))
                                    <p class="mt-1 text-xs text-ink-soft dark:text-slate-400">Đáp án: {{ $item['answer'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    @elseif ($previewType === \App\Enums\ArtifactType::Exam)
                        @php
                            $examSettings = is_array($preview->payload['settings'] ?? null) ? $preview->payload['settings'] : [];
                            $examQuestionNo = 0;
                            $examAnswers = [];
                            $examIncludedPoints = 0.0;
                            $examIncludedCount = 0;
                            foreach (($preview->payload['sections'] ?? []) as $examSection) {
                                foreach (($examSection['questions'] ?? []) as $examQuestion) {
                                    if (($examQuestion['included'] ?? true) === false) {
                                        continue;
                                    }
                                    $examIncludedCount++;
                                    $examIncludedPoints += (float) ($examQuestion['points'] ?? 0);
                                    $examAnswers[] = trim(($examQuestion['answer'] ?? '') !== '' ? (string) $examQuestion['answer'] : collect($examQuestion['options'] ?? [])->firstWhere('is_correct')['content'] ?? '');
                                }
                            }
                        @endphp

                        <div class="space-y-4" x-data="{ showAnswers: false }">
                            <div class="rounded-[10px] border border-rule p-3 text-center dark:border-night-700">
                                <p class="font-serif text-base font-semibold text-ink dark:text-white">{{ $preview->title }}</p>
                                <p class="mt-1 text-xs text-ink-soft dark:text-slate-400">
                                    {{ $preview->subject?->name }}
                                    @if (filled($examSettings['duration_minutes'] ?? null))
                                        · Thời gian: {{ $examSettings['duration_minutes'] }} phút
                                    @endif
                                    · Tổng điểm: <span class="tnum">{{ rtrim(rtrim(number_format($examIncludedPoints, 2, ',', '.'), '0'), ',') }}</span>
                                    @if ((float) ($examSettings['total_points'] ?? 0) > 0 && abs((float) $examSettings['total_points'] - $examIncludedPoints) >= 0.01)
                                        <span class="text-warning dark:text-amber-300">(đề đặt {{ $examSettings['total_points'] }} điểm)</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-[11px] text-ink-faint dark:text-slate-500">
                                    Họ và tên: ..................................... Số báo danh: ............
                                </p>
                            </div>

                            @foreach ($preview->payload['sections'] ?? [] as $sectionIndex => $section)
                                <section wire:key="exam-preview-section-{{ $sectionIndex }}">
                                    <h3 class="text-sm font-semibold uppercase text-ink dark:text-white">{{ $section['title'] }}</h3>
                                    @if (filled($section['instructions'] ?? null))
                                        <p class="mt-1 text-xs italic text-ink-soft dark:text-slate-400">{{ $section['instructions'] }}</p>
                                    @endif

                                    @foreach ($section['questions'] ?? [] as $qIndex => $item)
                                        @php $examQuestionNo++ @endphp
                                        <div class="mt-2 {{ ($item['included'] ?? true) === false ? 'opacity-50' : '' }}">
                                            <p class="text-sm text-ink dark:text-slate-100">
                                                <span class="tnum font-semibold">Câu {{ $examQuestionNo }}.</span>
                                                {{ $item['content'] }}
                                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">({{ rtrim(rtrim(number_format((float) ($item['points'] ?? 0), 2, ',', '.'), '0'), ',') }} điểm)</span>
                                                @if (($item['included'] ?? true) === false)
                                                    <span class="ml-1 text-xs text-signal dark:text-red-400">(không dùng)</span>
                                                @endif
                                            </p>
                                            @if (($item['type'] ?? '') === 'multiple_choice')
                                                <ul class="mt-1 space-y-0.5">
                                                    @foreach ($item['options'] ?? [] as $optionIndex => $option)
                                                        <li class="flex items-start gap-2 text-sm text-ink-soft dark:text-slate-300"
                                                            :class="{{ ($option['is_correct'] ?? false) ? 'true' : 'false' }} && showAnswers ? 'font-semibold text-success' : ''">
                                                            <span class="tnum w-4 shrink-0">{{ chr(65 + $optionIndex) }}.</span>
                                                            <span>{{ $option['content'] }}</span>
                                                            <span x-show="{{ ($option['is_correct'] ?? false) ? 'true' : 'false' }} && showAnswers" x-cloak
                                                                class="text-xs font-normal text-success">(đúng)</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if (filled($item['answer'] ?? null))
                                                <p x-show="showAnswers" x-cloak class="mt-1 text-xs text-success">Đáp án: {{ $item['answer'] }}</p>
                                            @endif
                                            @if (filled($item['explanation'] ?? null))
                                                <p x-show="showAnswers" x-cloak class="mt-0.5 text-xs text-ink-faint dark:text-slate-400">{{ $item['explanation'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </section>
                            @endforeach

                            <div class="notebook-print-hide flex flex-wrap items-center justify-between gap-2 border-t border-rule pt-3 dark:border-night-700">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                                    <input type="checkbox" x-model="showAnswers" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                    Xem đáp án
                                </label>
                                <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $examIncludedCount }} câu được dùng</span>
                            </div>

                            <div x-show="showAnswers" x-cloak class="exam-answer-key">
                                <h4 class="text-sm font-semibold text-ink dark:text-white">Bảng đáp án</h4>
                                <div class="mt-2 grid grid-cols-5 gap-1 text-xs sm:grid-cols-8">
                                    @foreach ($examAnswers as $index => $answer)
                                        <div class="rounded-[6px] bg-paper-2 px-1.5 py-1 text-ink dark:bg-white/5 dark:text-slate-200">
                                            <span class="tnum font-semibold">{{ $index + 1 }}.</span> {{ $answer !== '' ? $answer : '—' }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif ($previewType === \App\Enums\ArtifactType::Flashcards)
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($preview->payload['cards'] ?? [] as $card)
                                <div class="rounded-[10px] border border-rule p-3 dark:border-night-700">
                                    <p class="text-sm font-medium text-ink dark:text-slate-100">{{ $card['front'] }}</p>
                                    <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">{{ $card['back'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($previewType === \App\Enums\ArtifactType::MindMap)
                        <ul class="space-y-1 text-sm text-ink-soft dark:text-slate-300">
                            @foreach ($preview->payload['nodes'] ?? [] as $node)
                                <li>{{ $node['label'] }}@if (! empty($node['parent'])) <span class="text-xs text-ink-faint">(thuộc {{ $node['parent'] }})</span>@endif</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="notebook-markdown text-sm leading-relaxed text-ink dark:text-slate-200">{!! \Illuminate\Support\Str::markdown((string) $preview->text_content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                </div>

                @unless ($preview->isPublished())
                    <div class="notebook-print-hide mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-rule pt-3 dark:border-night-700">
                        <button type="button" wire:click="startEditingPreview" class="btn btn-outline mr-auto">Sửa bản nháp</button>
                        <button type="button" wire:click="regenerate({{ $preview->id }})" wire:confirm="Tạo lại nội dung này bằng AI? Bản nháp hiện tại sẽ bị thay thế."
                            class="btn btn-ghost text-xs">Tạo lại</button>
                        @if (in_array($previewType, [\App\Enums\ArtifactType::Document, \App\Enums\ArtifactType::StudyGuide, \App\Enums\ArtifactType::Briefing], true))
                            <label class="mr-auto flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                                <input type="checkbox" wire:model="publishPublic" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                Công khai cho học sinh
                            </label>
                        @endif
                        @if ($previewType === \App\Enums\ArtifactType::Exam)
                            <span class="mr-auto text-xs text-ink-faint dark:text-slate-500">
                                @if ($preview->isPublished())
                                    Đề đã tạo trong danh sách Đề thi.
                                @else
                                    Tạo đề nháp rồi mở trình soạn đề để chỉnh lịch, trộn câu và giao cho học sinh.
                                @endif
                            </span>
                            <button type="button" wire:click="publishAndOpen({{ $preview->id }})"
                                wire:loading.attr="disabled" wire:target="publishAndOpen({{ $preview->id }})" class="btn btn-primary">
                                Mở trong trình soạn đề
                            </button>
                        @else
                            <span class="mr-auto text-xs text-ink-faint dark:text-slate-500">Xuất bản vào: {{ $previewType->publishTarget() }}</span>
                            <button type="button" wire:click="publish({{ $preview->id }})" class="btn btn-primary" wire:loading.attr="disabled" wire:target="publish">
                                Xuất bản
                            </button>
                        @endif
                    </div>
                @endunless
                <div class="notebook-print-hide mt-3 flex justify-end gap-2 border-t border-rule pt-3 dark:border-night-700">
                    <a href="{{ route('studio.ai.artifacts.export', ['artifact' => $preview->id, 'format' => 'docx']) }}" class="btn btn-outline text-xs">Tải DOCX</a>
                    <button type="button" onclick="window.print()" class="btn btn-outline text-xs">In / Lưu PDF</button>
                </div>
                @endif
            </div>
        </div>
    @endif
</div>
