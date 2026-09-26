<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Sơ đồ kiến thức</h1>
            <p class="page-sub">
                @if ($subject)
                    Bảng trắng tạo node và liên kết cho môn <span class="font-medium" style="color: {{ $subject->color }}">{{ $subject->name }}</span>.
                @else
                    Bảng trắng tạo node và liên kết kiến thức.
                @endif
            </p>
        </div>
        @if ($canCreate)
            <button type="button" wire:click="openCreate" class="btn btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Tạo sơ đồ
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($sharedUrl)
        <div class="alert alert-success">
            <p class="font-medium">Liên kết chia sẻ</p>
            <code class="mt-2 block select-all overflow-x-auto rounded-[10px] bg-white px-3 py-2 text-xs text-ink dark:bg-night-900 dark:text-slate-200">{{ $sharedUrl }}</code>
        </div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($maps as $map)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="map-{{ $map->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-paper-2 text-ink-soft dark:bg-white/5 dark:text-slate-300">
                        <x-icon name="map" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $map->title }}</p>
                            <span class="chip chip-neutral">{{ $map->visibility->label() }}</span>
                        </div>
                        @if ($map->description)
                            <p class="mt-0.5 line-clamp-1 text-sm text-ink-soft dark:text-slate-400">{{ $map->description }}</p>
                        @endif
                        <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                            {{ $map->owner?->name }} <span class="mx-1">—</span> phiên bản {{ $map->current_version }}
                            @if ($map->updated_at)<span class="mx-1">—</span>{{ $map->updated_at->diffForHumans() }}@endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="{{ route('maps.edit', $map) }}" wire:navigate class="btn btn-primary px-3.5 py-2 text-xs">Mở</a>
                        @can('update', $map)
                            <button type="button" wire:click="openEdit({{ $map->id }})" class="btn btn-outline px-3.5 py-2 text-xs">Sửa</button>
                        @endcan
                        @can('share', $map)
                            <button type="button" wire:click="share({{ $map->id }})" class="btn btn-ghost px-3 py-2 text-xs">Chia sẻ</button>
                        @endcan
                        <button type="button" wire:click="duplicate({{ $map->id }})" class="btn btn-ghost px-3 py-2 text-xs">Nhân bản</button>
                        @can('delete', $map)
                            <button type="button" wire:click="delete({{ $map->id }})" wire:confirm="Xóa sơ đồ {{ $map->title }}?"
                                class="btn btn-ghost px-3 py-2 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có sơ đồ nào. Tạo sơ đồ đầu tiên để bắt đầu.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-md rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa sơ đồ' : 'Tạo sơ đồ' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="m-title">Tên sơ đồ</label>
                        <input id="m-title" type="text" class="input" wire:model="title" placeholder="Sơ đồ chuyên đề 1">
                        @error('title') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="m-description">Mô tả</label>
                        <textarea id="m-description" rows="2" class="input" wire:model="description"></textarea>
                    </div>
                    <div>
                        <label class="label" for="m-visibility">Hiển thị</label>
                        <select id="m-visibility" class="input" wire:model="visibility">
                            @foreach ($visibilities as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">{{ collect($visibilities)->firstWhere('value', $visibility)?->description() }}</p>
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
