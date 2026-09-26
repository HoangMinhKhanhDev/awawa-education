<div class="space-y-6">
    <x-teacher.tabs active="documents" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Tài liệu học tập</h1>
            <p class="page-sub">Tài liệu lưu riêng theo môn <span class="font-medium" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tải tài liệu
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($documents as $document)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="doc-{{ $document->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-paper-2 text-ink-soft dark:bg-white/5 dark:text-slate-300">
                        <x-icon name="mail" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $document->title }}</p>
                            @if ($document->category)
                                <span class="chip chip-neutral">{{ $document->category }}</span>
                            @endif
                            @unless ($document->is_public)
                                <span class="chip chip-warning">Riêng tư</span>
                            @endunless
                        </div>
                        <p class="tnum mt-0.5 truncate text-xs text-ink-faint dark:text-slate-500">
                            {{ $document->original_name }} — {{ $document->sizeForHumans() }}@if ($document->creator)<span class="mx-1.5">—</span>{{ $document->creator->name }}@endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="btn btn-outline px-3.5 py-2 text-xs">Mở</a>
                        <button type="button" wire:click="togglePublic({{ $document->id }})" class="btn btn-ghost px-3 py-2 text-xs">
                            {{ $document->is_public ? 'Chuyển riêng tư' : 'Công khai' }}
                        </button>
                        <button type="button" wire:click="delete({{ $document->id }})" wire:confirm="Xóa tài liệu {{ $document->title }}?"
                            class="btn btn-ghost px-3 py-2 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có tài liệu nào.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">Tải tài liệu</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="d-title">Tiêu đề</label>
                        <input id="d-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="d-category">Danh mục</label>
                            <input id="d-category" type="text" class="input" wire:model="category" placeholder="Chuyên đề">
                        </div>
                        <div>
                            <label class="label" for="d-file">Tệp (tối đa 20MB)</label>
                            <input id="d-file" type="file" class="input" wire:model="file">
                            @error('file') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="label" for="d-description">Mô tả</label>
                        <textarea id="d-description" rows="2" class="input" wire:model="description"></textarea>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                        <input type="checkbox" wire:model="isPublic" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        Công khai cho học sinh trong đội
                    </label>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save,file">Tải lên</span>
                            <span wire:loading wire:target="save,file">Đang tải…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
