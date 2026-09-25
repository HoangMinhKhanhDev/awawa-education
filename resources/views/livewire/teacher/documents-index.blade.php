<div class="space-y-6">
    <x-teacher.tabs active="documents" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Tài liệu học tập</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tài liệu được lưu riêng theo môn {{ $subject?->name }}.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tải tài liệu
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse ($documents as $document)
                <div class="flex flex-wrap items-center gap-3 p-4" wire:key="doc-{{ $document->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="mail" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $document->title }}</p>
                            @if ($document->category)
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $document->category }}</span>
                            @endif
                            @unless ($document->is_public)
                                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Riêng tư</span>
                            @endunless
                        </div>
                        <p class="truncate text-xs text-slate-400">
                            {{ $document->original_name }} · {{ $document->sizeForHumans() }}
                            @if ($document->creator) · {{ $document->creator->name }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="btn btn-outline px-3 py-1.5 text-xs">Mở</a>
                        <button type="button" wire:click="togglePublic({{ $document->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $document->is_public ? 'Chuyển riêng tư' : 'Công khai' }}
                        </button>
                        <button type="button" wire:click="delete({{ $document->id }})" wire:confirm="Xóa tài liệu {{ $document->title }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Chưa có tài liệu nào.</div>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tải tài liệu</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="d-title">Tiêu đề</label>
                        <input id="d-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="d-category">Danh mục</label>
                            <input id="d-category" type="text" class="input" wire:model="category" placeholder="Chuyên đề">
                        </div>
                        <div>
                            <label class="label" for="d-file">Tệp (tối đa 20MB)</label>
                            <input id="d-file" type="file" class="input" wire:model="file">
                            @error('file') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="label" for="d-description">Mô tả</label>
                        <textarea id="d-description" rows="2" class="input" wire:model="description"></textarea>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="isPublic" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                        Công khai cho học sinh trong đội
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
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
