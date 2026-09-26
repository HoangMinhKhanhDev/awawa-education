<div class="flex h-full min-h-0 flex-col">
    <div class="flex h-12 shrink-0 items-center justify-between px-4">
        <h2 class="text-sm font-semibold text-ink dark:text-white">Nguồn</h2>
        <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $sources->count() }}/{{ $maxSources }}</span>
    </div>

    <div class="shrink-0 space-y-2 px-4 pb-3">
        @if (session('notebook_status'))
            <div class="alert alert-success">{{ session('notebook_status') }}</div>
        @endif

        @if ($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif

        <button type="button" wire:click="$set('addType', 'file')" class="btn btn-outline w-full justify-start">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm nguồn
        </button>

        <div class="flex gap-1.5">
            <input type="text" class="input py-2 text-sm" wire:model="webTopic" wire:keydown.enter.prevent="searchWeb"
                placeholder="Tìm nguồn mới trên web">
            <button type="button" wire:click="searchWeb" class="btn btn-outline shrink-0 px-3" title="Tìm trên web"
                wire:loading.attr="disabled" wire:target="searchWeb">
                <x-icon name="bolt" class="h-4 w-4" />
            </button>
        </div>
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
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="line-clamp-1 text-sm font-medium text-ink dark:text-slate-100">{{ $result['title'] }}</span>
                                @if (! empty($result['keep']))
                                    <span class="chip chip-success">Nên dùng</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block truncate text-[11px] text-ink-faint dark:text-slate-500">{{ $result['url'] }}</span>
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
                <div class="group flex items-start gap-2.5 px-4 py-3 hover:bg-paper-2 dark:hover:bg-white/5" wire:key="source-{{ $source->id }}">
                    <label class="mt-0.5 flex cursor-pointer items-center" title="Bật/tắt nguồn">
                        <input type="checkbox" @checked($source->is_enabled) wire:click="toggle({{ $source->id }})"
                            class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                    </label>

                    <button type="button" wire:click="view({{ $source->id }})" class="min-w-0 flex-1 text-left">
                        <p class="line-clamp-2 text-sm text-ink dark:text-slate-100">{{ $source->title }}</p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-ink-faint dark:text-slate-500">
                            <span>{{ $source->typeLabel() }}</span>
                            @if ($source->status === 'failed')
                                <span class="font-medium text-signal">Lỗi trích nội dung</span>
                            @else
                                <span class="tnum">{{ $source->chunks_count }} đoạn</span>
                            @endif
                        </p>
                    </button>

                    <button type="button" wire:click="remove({{ $source->id }})" wire:confirm="Xóa nguồn này?"
                        class="rounded-[10px] p-1.5 text-ink-faint opacity-0 transition-opacity hover:bg-signal-soft hover:text-signal group-hover:opacity-100 dark:hover:bg-red-500/10" title="Xóa">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                @if ($webResults === [])
                    <p class="empty">Chưa có nguồn. Thêm tệp, dán văn bản, chọn tài liệu trong môn hoặc tìm trên web.</p>
                @endif
            @endforelse
        </div>
    </div>

    @if ($addType !== '')
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="w-full max-w-lg rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">Thêm nguồn</h2>
                    <button type="button" wire:click="$set('addType', '')" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="tabs mb-4">
                    @foreach (['file' => 'Tải tệp', 'text' => 'Dán văn bản', 'document' => 'Tài liệu trong môn'] as $key => $label)
                        <button type="button" wire:click="$set('addType', '{{ $key }}')"
                            class="tab {{ $addType === $key ? 'tab-active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>

                @if ($addType === 'file')
                    <div class="space-y-4">
                        <div>
                            <label class="label" for="src-title">Tiêu đề (không bắt buộc)</label>
                            <input id="src-title" type="text" class="input" wire:model="title" placeholder="Để trống sẽ lấy tên tệp">
                        </div>
                        <div>
                            <label class="label" for="src-file">Tệp (PDF, DOCX, TXT, MD — tối đa {{ config('awawa.notebook.max_file_mb') }}MB)</label>
                            <input id="src-file" type="file" class="input" wire:model="file" accept=".pdf,.docx,.txt,.md,.csv">
                            @error('file') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            <p wire:loading wire:target="file" class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Đang tải lên…</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="addFile" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addFile,file">
                                <span wire:loading.remove wire:target="addFile">Thêm nguồn</span>
                                <span wire:loading wire:target="addFile">Đang xử lý…</span>
                            </button>
                        </div>
                    </div>
                @elseif ($addType === 'text')
                    <div class="space-y-4">
                        <div>
                            <label class="label" for="src-text-title">Tiêu đề</label>
                            <input id="src-text-title" type="text" class="input" wire:model="title" placeholder="VD: Chuyên đề bất đẳng thức">
                            @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="src-text">Nội dung</label>
                            <textarea id="src-text" rows="8" class="input" wire:model="text" placeholder="Dán nội dung vào đây…"></textarea>
                            @error('text') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="addText" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addText">
                                <span wire:loading.remove wire:target="addText">Thêm nguồn</span>
                                <span wire:loading wire:target="addText">Đang xử lý…</span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        <div>
                            <label class="label" for="src-doc">Chọn tài liệu trong môn</label>
                            <select id="src-doc" class="input" wire:model="documentId">
                                <option value="">— Chọn tài liệu —</option>
                                @foreach ($documents as $document)
                                    <option value="{{ $document->id }}">{{ $document->title }}</option>
                                @endforeach
                            </select>
                            @error('documentId') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="addDocument" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addDocument">
                                <span wire:loading.remove wire:target="addDocument">Thêm nguồn</span>
                                <span wire:loading wire:target="addDocument">Đang xử lý…</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($viewing)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="flex max-h-[88vh] w-full max-w-2xl flex-col rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">{{ $viewing->title }}</h2>
                        <p class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $viewing->chunks->count() }} đoạn · {{ number_format($viewing->char_count) }} ký tự</p>
                    </div>
                    <button type="button" wire:click="closeViewer" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto">
                    @foreach ($viewing->chunks as $chunk)
                        <div class="rounded-[10px] border border-rule p-3 dark:border-night-700">
                            <p class="tnum mb-1 text-[11px] text-ink-faint dark:text-slate-500">Đoạn {{ $chunk->position + 1 }}</p>
                            <p class="whitespace-pre-line text-sm leading-relaxed text-ink-soft dark:text-slate-300">{{ $chunk->content }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
