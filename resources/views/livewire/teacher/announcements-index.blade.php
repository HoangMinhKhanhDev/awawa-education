<div class="space-y-6">
    <x-teacher.tabs active="announcements" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Thông báo</h1>
            <p class="page-sub">Gửi thông báo tới học sinh trong đội tuyển môn <span class="font-medium" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo thông báo
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($announcements as $announcement)
                <article class="px-5 py-4" wire:key="announcement-{{ $announcement->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-[17px] font-semibold text-ink dark:text-white">{{ $announcement->title }}</h2>
                                @if ($announcement->is_pinned)
                                    <span class="chip chip-brand">Đã ghim</span>
                                @endif
                                <span class="chip {{ $announcement->isPublished() ? 'chip-success' : 'chip-neutral' }}">
                                    {{ $announcement->isPublished() ? 'Đã đăng' : 'Bản nháp' }}
                                </span>
                            </div>
                            <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-ink-soft dark:text-slate-300">{{ $announcement->body }}</p>
                            <p class="mt-2 text-xs text-ink-faint dark:text-slate-500">
                                {{ $announcement->creator?->name }}@if ($announcement->published_at)<span class="mx-1.5">—</span>{{ $announcement->published_at->diffForHumans() }}@endif
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-1.5">
                            <button type="button" wire:click="togglePublish({{ $announcement->id }})" class="btn btn-ghost px-3 py-2 text-xs">
                                {{ $announcement->isPublished() ? 'Ẩn' : 'Đăng' }}
                            </button>
                            <button type="button" wire:click="openEdit({{ $announcement->id }})" class="btn btn-outline px-3.5 py-2 text-xs">Sửa</button>
                            <button type="button" wire:click="delete({{ $announcement->id }})" wire:confirm="Xóa thông báo này?"
                                class="btn btn-ghost px-3 py-2 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                        </div>
                    </div>
                </article>
            @empty
                <p class="empty">Chưa có thông báo nào.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-lg rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa thông báo' : 'Tạo thông báo' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="an-title">Tiêu đề</label>
                        <input id="an-title" type="text" class="input" wire:model="title">
                        @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="an-body">Nội dung</label>
                        <textarea id="an-body" rows="5" class="input" wire:model="body"></textarea>
                        @error('body') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="isPinned" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Ghim thông báo
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="publishNow" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Đăng ngay
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
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
