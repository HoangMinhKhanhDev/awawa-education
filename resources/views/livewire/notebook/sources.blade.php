<div class="flex h-full min-h-0 flex-col">
    <div class="flex h-12 shrink-0 items-center justify-between gap-2 px-4">
        <h2 class="text-sm font-semibold text-ink dark:text-white">Nguồn</h2>
        <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $allSourcesCount }}/{{ $maxSources }}</span>
    </div>

    <div class="shrink-0 space-y-2 px-4 pb-3">
        @if (session('notebook_status'))
            <div class="alert alert-success">{{ session('notebook_status') }}</div>
        @endif

        @if ($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif

        <button type="button" wire:click="openAddForm('')" @disabled($allSourcesCount >= $maxSources)
            class="btn btn-outline w-full justify-start disabled:opacity-50">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm nguồn
        </button>

        <div class="flex items-center gap-1.5">
            <div class="relative min-w-0 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ink-faint dark:text-slate-500" />
                <input type="text" class="input py-1.5 pl-8 text-sm" wire:model.live.debounce.400ms="sourceQuery"
                    placeholder="Tìm trong danh sách nguồn" aria-label="Tìm trong danh sách nguồn">
            </div>
            <select class="input w-auto shrink-0 py-1.5 text-xs" wire:model.live="sourceTypeFilter" aria-label="Lọc theo loại nguồn">
                <option value="">Tất cả</option>
                <option value="web">Trang web</option>
                <option value="file">Tệp</option>
                <option value="text">Văn bản</option>
                <option value="document">Tài liệu</option>
                <option value="question">Câu hỏi</option>
                <option value="exam">Đề thi</option>
            </select>
        </div>

        @if ($sources->isNotEmpty())
            <div class="flex items-center justify-between text-[11px] text-ink-faint dark:text-slate-500">
                <span class="tnum">{{ $sources->where('is_enabled', true)->count() }} / {{ $sources->count() }} đang dùng</span>
                <span class="flex gap-2">
                    <button type="button" wire:click="selectAllVisible(true)" title="Bật tất cả nguồn đang hiển thị"
                        class="hover:text-brand-700 dark:hover:text-brand-300">Bật tất cả</button>
                    <button type="button" wire:click="selectAllVisible(false)" title="Tắt tất cả nguồn đang hiển thị"
                        class="hover:text-brand-700 dark:hover:text-brand-300">Tắt tất cả</button>
                </span>
            </div>
        @endif
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        @if ($webResults !== [])
            <div class="space-y-2 border-y border-rule bg-paper-2/50 p-3 dark:border-night-700 dark:bg-white/5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-ink dark:text-slate-200">Kết quả tìm kiếm</p>
                    <button type="button" wire:click="$set('webResults', [])" class="text-[11px] text-ink-faint hover:text-signal dark:text-slate-500">Bỏ</button>
                </div>

                @foreach ($webResults as $index => $result)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-[10px] border border-rule bg-white p-2.5 dark:border-night-700 dark:bg-night-800" wire:key="web-{{ $index }}">
                        <input type="checkbox" value="{{ $index }}" wire:model="webSelected"
                            class="mt-0.5 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        <span class="min-w-0">
                            <span class="line-clamp-2 text-sm font-medium text-ink dark:text-slate-100">{{ $result['title'] }}</span>
                            <span class="mt-0.5 block truncate text-[11px] text-ink-faint dark:text-slate-500">{{ $result['url'] }}</span>
                            <span class="mt-1 line-clamp-2 text-xs leading-relaxed text-ink-soft dark:text-slate-400">{{ $result['content'] }}</span>
                            @if (! empty($result['reason']))
                                <span class="mt-1 block text-[11px] text-ink-faint dark:text-slate-500">{{ $result['reason'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach

                <button type="button" wire:click="addWebSources" class="btn btn-primary w-full text-xs" wire:loading.attr="disabled" wire:target="addWebSources">
                    Thêm {{ count($webSelected) }} nguồn đã chọn
                </button>
            </div>
        @endif

        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($sources as $source)
                <div class="group relative flex items-start gap-2.5 px-3 py-2.5 hover:bg-paper-2 dark:hover:bg-white/5" wire:key="source-{{ $source->id }}">
                    <input type="checkbox" wire:click="toggle({{ $source->id }})" @checked($source->is_enabled)
                        class="mt-0.5 h-4 w-4 flex-none rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700"
                        aria-label="Bật nguồn {{ $source->title }}">

                    <div class="min-w-0 flex-1">
                        @if ($editingSourceId === $source->id)
                            <form wire:submit="renameSource" class="flex gap-1.5">
                                <input type="text" class="input py-1 text-xs" wire:model="sourceTitleDraft" aria-label="Tên nguồn">
                                <button type="submit" class="btn btn-primary px-2 py-1 text-[11px]">Lưu</button>
                            </form>
                            @error('sourceTitleDraft') <p class="mt-1 text-[11px] text-signal">{{ $message }}</p> @enderror
                        @else
                            <button type="button" wire:click="view({{ $source->id }})" class="w-full text-left">
                                <span class="flex items-start gap-1.5">
                                    <x-icon :name="$source->typeIcon()" class="mt-0.5 h-3.5 w-3.5 flex-none text-ink-faint dark:text-slate-500" />
                                    <span class="line-clamp-2 text-sm leading-snug text-ink dark:text-slate-100">{{ $source->title }}</span>
                                </span>
                                <span class="mt-1 flex items-center gap-1.5 overflow-hidden whitespace-nowrap">
                                    <span class="status-chip {{ $source->statusClass() }}">{{ $source->statusLabel() }}</span>
                                    <span class="truncate text-[11px] text-ink-faint dark:text-slate-500">{{ $source->typeLabel() }} · {{ $source->chunks_count }} đoạn · {{ number_format($source->char_count) }} ký tự</span>
                                </span>
                            </button>
                        @endif
                    </div>

                    <div class="relative flex-none" x-data="{ menu: false }">
                        <button type="button" @click="menu = ! menu" @click.outside="menu = false"
                            class="rounded-[8px] p-1 text-ink-faint opacity-0 transition-opacity hover:bg-paper-2 hover:text-ink focus:opacity-100 group-hover:opacity-100 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                            aria-label="Tuỳ chọn nguồn {{ $source->title }}">
                            <x-icon name="dots" class="h-4 w-4" />
                        </button>

                        <div x-show="menu" x-cloak @click.outside="menu = false"
                            class="panel absolute right-0 top-7 z-40 w-52 overflow-hidden py-1 text-left shadow-lg" role="menu">
                            <button type="button" wire:click="view({{ $source->id }})" @click="menu = false"
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                <x-icon name="book" class="h-3.5 w-3.5" /> Xem nội dung
                            </button>
                            <button type="button" wire:click="startRenaming({{ $source->id }})" @click="menu = false"
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                <x-icon name="pencil" class="h-3.5 w-3.5" /> Đổi tên
                            </button>
                            @if ($source->hasOriginal())
                                <a href="{{ $source->originalUrl() }}" target="_blank" rel="noopener noreferrer" @click="menu = false"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                    <x-icon name="external" class="h-3.5 w-3.5" /> Mở bản gốc
                                </a>
                            @endif
                            @if ($source->status === 'ready')
                                <button type="button" wire:click="askAbout({{ $source->id }})" @click="menu = false"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                    <x-icon name="help" class="h-3.5 w-3.5" /> Hỏi riêng nguồn này
                                </button>
                            @endif
                            @if ($source->status === 'failed')
                                <button type="button" wire:click="retrySource({{ $source->id }})" wire:loading.attr="disabled" wire:target="retrySource" @click="menu = false"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                    <x-icon name="refresh" class="h-3.5 w-3.5" /> Thử lại
                                </button>
                            @endif
                            <button type="button" wire:click="moveSource({{ $source->id }}, 'up')" @click="menu = false"
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                <x-icon name="arrow-up" class="h-3.5 w-3.5" /> Đưa lên
                            </button>
                            <button type="button" wire:click="moveSource({{ $source->id }}, 'down')" @click="menu = false"
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5" role="menuitem">
                                <x-icon name="arrow-down" class="h-3.5 w-3.5" /> Đưa xuống
                            </button>
                            <button type="button" wire:click="remove({{ $source->id }})" wire:confirm="Xoá nguồn này?" @click="menu = false"
                                class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" role="menuitem">
                                <x-icon name="trash" class="h-3.5 w-3.5" /> Xoá
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                @if ($webResults === [])
                    <p class="empty">
                        {{ $allSourcesCount > 0 ? 'Không có nguồn nào khớp bộ lọc.' : 'Chưa có nguồn. Bấm “Thêm nguồn” để bắt đầu.' }}
                    </p>
                @endif
            @endforelse
        </div>
    </div>

    @if ($addOpen && $addType === '')
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-[14px] bg-white p-5 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">Thêm nguồn</h2>
                    <button type="button" wire:click="closeAddForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        'text' => ['Dán văn bản', 'doc', 'Dán nội dung bạn đã sao chép.'],
                        'file' => ['Tải tệp từ máy', 'upload', 'Chọn nhiều tệp PDF, DOCX, TXT, MD, CSV.'],
                        'url' => ['Dán đường dẫn', 'link', 'Dán một hoặc nhiều link, mỗi dòng một link.'],
                        'internal' => ['Từ trong ứng dụng', 'layers', 'Tài liệu, câu hỏi hoặc đề thi đã có trong môn.'],
                    ] as $key => [$label, $icon, $description])
                        <button type="button" wire:click="openAddForm('{{ $key }}')"
                            class="flex items-start gap-3 rounded-[12px] border border-rule p-3 text-left transition-colors hover:bg-paper-2 dark:border-night-700 dark:hover:bg-white/5">
                            <x-icon :name="$icon" class="mt-0.5 h-5 w-5 flex-none text-brand-600 dark:text-brand-400" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-ink dark:text-slate-100">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-ink-faint dark:text-slate-500">{{ $description }}</span>
                            </span>
                        </button>
                    @endforeach

                    <button type="button" disabled title="Sẽ có sau khi kết nối Google Drive"
                        class="flex cursor-not-allowed items-start gap-3 rounded-[12px] border border-dashed border-rule p-3 text-left opacity-60">
                        <x-icon name="external" class="mt-0.5 h-5 w-5 flex-none text-ink-faint dark:text-slate-500" />
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 text-sm font-medium text-ink-soft dark:text-slate-300">
                                Google Drive
                                <span class="chip chip-neutral text-[10px]">Sắp có</span>
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-ink-faint dark:text-slate-500">Chọn tệp trực tiếp trên Drive của bạn.</span>
                        </span>
                    </button>
                </div>

                <div class="mt-5 border-t border-rule pt-4 dark:border-night-700">
                    <label class="label" for="src-web-topic">Hoặc tìm nguồn theo chủ đề</label>
                    <div class="flex gap-1.5">
                        <input id="src-web-topic" type="text" class="input" wire:model="webTopic"
                            wire:keydown.enter.prevent="searchWeb" placeholder="VD: định lý Cauchy ứng dụng vào bất đẳng thức">
                        <button type="button" wire:click="searchWeb" class="btn btn-primary flex-none px-3" wire:loading.attr="disabled" wire:target="searchWeb">
                            <span wire:loading.remove wire:target="searchWeb">Tìm</span>
                            <span wire:loading wire:target="searchWeb">…</span>
                        </button>
                    </div>
                    @if (! $webConfigured)
                        <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Chưa cấu hình Tavily API key nên chưa tìm được nguồn web.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($addOpen && $addType !== '')
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-[14px] bg-white p-5 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <button type="button" wire:click="$set('addType', '')" class="flex items-center gap-1 text-sm text-ink-faint hover:text-ink dark:text-slate-400 dark:hover:text-white">
                        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Quay lại
                    </button>
                    <button type="button" wire:click="closeAddForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    @if ($addType === 'text')
                        <div>
                            <label class="label" for="src-text-title">Tiêu đề</label>
                            <input id="src-text-title" type="text" class="input" wire:model="title" placeholder="VD: Chuyên đề bất đẳng thức">
                            @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="src-text">Nội dung</label>
                            <textarea id="src-text" rows="10" class="input" wire:model="text" placeholder="Dán nội dung vào đây…"></textarea>
                            @error('text') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="addText" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addText">Thêm nguồn</button>
                        </div>
                    @elseif ($addType === 'file')
                        <div class="space-y-4">
                            <div>
                                <label class="label" for="src-file">Tệp (PDF, DOCX, TXT, MD, CSV — tối đa {{ $maxFileMegabytes }}MB mỗi tệp)</label>
                                <input id="src-file" type="file" class="input" wire:model="files" multiple
                                    accept=".pdf,.docx,.txt,.md,.csv">
                                @error('files') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                                @error('files.*') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                                <p wire:loading wire:target="files" class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Đang tải lên…</p>
                            </div>
                            @if (count($files) === 1)
                                <div>
                                    <label class="label" for="src-file-title">Tên nguồn (không bắt buộc)</label>
                                    <input id="src-file-title" type="text" class="input" wire:model="title" placeholder="Để trống sẽ lấy tên tệp">
                                </div>
                            @else
                                <p class="text-xs text-ink-faint dark:text-slate-500">Mỗi tệp sẽ thành một nguồn riêng với tên theo tên tệp.</p>
                            @endif
                            <div class="flex justify-end">
                                <button type="button" wire:click="addFiles" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addFiles,files">
                                    <span wire:loading.remove wire:target="addFiles">Thêm {{ count($files) ?: '' }} nguồn</span>
                                    <span wire:loading wire:target="addFiles">Đang xử lý…</span>
                                </button>
                            </div>
                        </div>
                    @elseif ($addType === 'url')
                        <div class="space-y-4">
                            <div>
                                <label class="label" for="src-urls">Đường dẫn</label>
                                <textarea id="src-urls" rows="6" class="input" wire:model="urlText"
                                placeholder="https://example.com/bai-giang&#10;https://example.com/luyen-tap"></textarea>
                                <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">
                                    Mỗi dòng một link. Hệ thống lấy toàn văn trang, link trùng hoặc không hợp lệ sẽ bị bỏ qua.
                                </p>
                            </div>
                            @if ($urlFailed !== [])
                                <div class="alert alert-warning text-xs">
                                    <p>Không lấy được nội dung từ:</p>
                                    <ul class="mt-1 list-disc pl-4">
                                        @foreach (array_slice($urlFailed, 0, 5) as $url)
                                            <li class="truncate">{{ $url }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($urlSkipped !== [])
                                <div class="alert alert-warning text-xs">
                                    <p>Đã bỏ qua vì trùng hoặc đã có trong notebook:</p>
                                    <ul class="mt-1 list-disc pl-4">
                                        @foreach (array_slice($urlSkipped, 0, 5) as $url)
                                            <li class="truncate">{{ $url }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="flex justify-end">
                                <button type="button" wire:click="addUrls" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addUrls">
                                    <span wire:loading.remove wire:target="addUrls">Thêm nguồn</span>
                                    <span wire:loading wire:target="addUrls">Đang lấy nội dung…</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="space-y-4">
                            <div>
                                <label class="label" for="src-internal">Loại nội dung trong môn</label>
                                <select id="src-internal" class="input" wire:model.live="internalType">
                                    <option value="document">Tài liệu</option>
                                    <option value="question">Câu hỏi</option>
                                    <option value="exam">Đề thi</option>
                                </select>
                            </div>

                            @if ($internalType === 'document')
                                <div>
                                    <label class="label" for="src-doc">Chọn tài liệu</label>
                                    <select id="src-doc" class="input" wire:model="documentId">
                                        <option value="">— Chọn tài liệu —</option>
                                        @foreach ($documents as $document)
                                            <option value="{{ $document->id }}">{{ $document->title }}</option>
                                        @endforeach
                                    </select>
                                    @error('documentId') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                                </div>
                            @elseif ($internalType === 'question')
                                <div>
                                    <label class="label" for="src-question">Chọn câu hỏi</label>
                                    <select id="src-question" class="input" wire:model="questionId">
                                        <option value="">— Chọn câu hỏi —</option>
                                        @foreach ($questions as $question)
                                            <option value="{{ $question->id }}">{{ \Illuminate\Support\Str::limit(strip_tags($question->content), 120) }}</option>
                                        @endforeach
                                    </select>
                                    @error('questionId') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <div>
                                    <label class="label" for="src-exam">Chọn đề thi</label>
                                    <select id="src-exam" class="input" wire:model="examId">
                                        <option value="">— Chọn đề thi —</option>
                                        @foreach ($exams as $exam)
                                            <option value="{{ $exam->id }}">{{ $exam->title }}</option>
                                        @endforeach
                                    </select>
                                    @error('examId') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <div class="flex justify-end">
                                <button type="button" wire:click="addInternalSource" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addInternalSource">
                                    Thêm làm nguồn
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($viewing)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="flex max-h-[88vh] w-full max-w-2xl flex-col rounded-t-[14px] bg-white p-5 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-serif text-lg font-semibold text-ink dark:text-white">{{ $viewing->title }}</h2>
                        <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                            {{ number_format($viewing->char_count) }} ký tự · {{ $viewing->chunks->count() }} đoạn
                        </p>
                    </div>
                    <button type="button" wire:click="closeViewer" class="flex-none rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="tabs mb-3">
                    <button type="button" wire:click="$set('viewerTab', 'highlights')" class="tab {{ $viewerTab === 'highlights' ? 'tab-active' : '' }}">
                        Đoạn nổi bật
                        @if ($viewingPassages->isNotEmpty())
                            <span class="tnum ml-1 text-[11px] opacity-70">{{ $viewingPassages->count() }}</span>
                        @endif
                    </button>
                    <button type="button" wire:click="$set('viewerTab', 'full')" class="tab {{ $viewerTab === 'full' ? 'tab-active' : '' }}">
                        Toàn văn
                        <span class="tnum ml-1 text-[11px] opacity-70">{{ $viewing->chunks->count() }}</span>
                    </button>
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto" wire:key="source-viewer-{{ $viewing->id }}-{{ $viewerTab }}-{{ $highlightChunkId }}"
                    x-init="$nextTick(() => document.getElementById('notebook-chunk-{{ $highlightChunkId }}')?.scrollIntoView({ block: 'center' }))">
                    @if ($viewerTab === 'highlights')
                        @forelse ($viewingPassages as $passage)
                            <article class="rounded-[10px] border-l-2 border-brand-500 bg-paper-2/60 py-2.5 pl-3 pr-3 dark:bg-white/5">
                                <p class="whitespace-pre-line text-sm leading-relaxed text-ink dark:text-slate-200">{{ $passage['text'] }}</p>
                                <button type="button" wire:click="jumpToChunk({{ $passage['chunk_id'] }})"
                                    class="mt-1.5 text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                                    Xem trong toàn văn · đoạn {{ $passage['position'] + 1 }}
                                </button>
                            </article>
                        @empty
                            <p class="empty">
                                Nguồn này quá ngắn nên chưa có câu trích nổi bật. Hãy xem tab “Toàn văn”.
                            </p>
                        @endforelse
                    @else
                        @foreach ($viewing->chunks as $chunk)
                            <div id="notebook-chunk-{{ $chunk->id }}" @class([
                                'rounded-[10px] border p-3 transition-colors dark:border-night-700',
                                'border-brand-500 bg-brand-50 ring-2 ring-brand-200 dark:bg-brand-500/10' => $highlightChunkId === $chunk->id,
                                'border-rule' => $highlightChunkId !== $chunk->id,
                            ])>
                                <p class="tnum mb-1 flex items-center gap-2 text-[11px] text-ink-faint dark:text-slate-500">
                                    <span>Đoạn {{ $chunk->position + 1 }}</span>
                                    @if ($chunk->is_highlight)
                                        <span class="status-chip status-success">Có trích dẫn</span>
                                    @endif
                                </p>
                                <p class="whitespace-pre-line text-sm leading-relaxed text-ink-soft dark:text-slate-300">{{ $chunk->content }}</p>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
