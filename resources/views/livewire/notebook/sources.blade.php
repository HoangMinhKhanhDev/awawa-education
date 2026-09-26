<div class="panel flex min-h-[420px] flex-col">
    <div class="flex items-center justify-between border-b border-rule px-4 py-3 dark:border-night-700">
        <h2 class="text-[15px] font-semibold text-ink dark:text-white">Nguồn</h2>
        <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $sources->count() }}/{{ $maxSources }}</span>
    </div>

    @if (session('notebook_status'))
        <div class="border-b border-rule px-4 py-3 dark:border-night-700">
            <div class="alert alert-success">{{ session('notebook_status') }}</div>
        </div>
    @endif

    @if ($error)
        <div class="border-b border-rule px-4 py-3 dark:border-night-700">
            <div class="alert alert-error">{{ $error }}</div>
        </div>
    @endif

    <div class="flex-1 divide-y divide-rule overflow-y-auto dark:divide-night-700">
        @forelse ($sources as $source)
            <div class="flex items-start gap-2.5 px-4 py-3" wire:key="source-{{ $source->id }}">
                <label class="mt-0.5 flex cursor-pointer items-center" title="Bật/tắt nguồn">
                    <input type="checkbox" @checked($source->is_enabled)
                        wire:click="toggle({{ $source->id }})"
                        class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                </label>

                <button type="button" wire:click="view({{ $source->id }})" class="min-w-0 flex-1 text-left">
                    <p class="line-clamp-2 text-sm font-medium text-ink dark:text-slate-100">{{ $source->title }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-ink-faint dark:text-slate-500">
                        <span class="chip chip-neutral">{{ $source->typeLabel() }}</span>
                        @if ($source->status === 'failed')
                            <span class="chip chip-signal">Lỗi</span>
                        @else
                            <span class="tnum">{{ $source->chunks_count }} đoạn</span>
                            <span class="tnum">{{ number_format($source->char_count) }} ký tự</span>
                        @endif
                    </p>
                </button>

                <button type="button" wire:click="remove({{ $source->id }})" wire:confirm="Xóa nguồn này?"
                    class="rounded-[10px] p-1.5 text-signal hover:bg-signal-soft dark:hover:bg-red-500/10" title="Xóa">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        @empty
            <p class="empty">Chưa có nguồn nào. Thêm tài liệu, dán văn bản hoặc chọn tài liệu trong môn.</p>
        @endforelse
    </div>

    <div class="border-t border-rule p-3 dark:border-night-700">
        <button type="button" wire:click="$set('addType', 'file')"
            class="btn btn-primary w-full">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm nguồn
        </button>
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
