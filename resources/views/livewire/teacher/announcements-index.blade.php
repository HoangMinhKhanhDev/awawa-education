<div class="space-y-6">
    <x-teacher.tabs active="announcements" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Thông báo</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Gửi thông báo tới học sinh trong đội tuyển môn {{ $subject?->name }}.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo thông báo
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($announcements as $announcement)
            <div class="card" wire:key="announcement-{{ $announcement->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-900 dark:text-white">{{ $announcement->title }}</h2>
                            @if ($announcement->is_pinned)
                                <span class="badge bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Ghim</span>
                            @endif
                            <span class="badge {{ $announcement->isPublished() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' }}">
                                {{ $announcement->isPublished() ? 'Đã đăng' : 'Bản nháp' }}
                            </span>
                        </div>
                        <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $announcement->body }}</p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ $announcement->creator?->name }}
                            @if ($announcement->published_at) · {{ $announcement->published_at->diffForHumans() }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="togglePublish({{ $announcement->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $announcement->isPublished() ? 'Ẩn' : 'Đăng' }}
                        </button>
                        <button type="button" wire:click="openEdit({{ $announcement->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $announcement->id }})" wire:confirm="Xóa thông báo này?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 dark:text-slate-400">Chưa có thông báo nào.</div>
        @endforelse
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingId ? 'Sửa thông báo' : 'Tạo thông báo' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="an-title">Tiêu đề</label>
                        <input id="an-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="an-body">Nội dung</label>
                        <textarea id="an-body" rows="5" class="input" wire:model="body"></textarea>
                        @error('body') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="isPinned" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            Ghim thông báo
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="publishNow" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            Đăng ngay
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Lưu</span>
                            <span wire:loading wire:target="save">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
