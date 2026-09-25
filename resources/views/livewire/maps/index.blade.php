<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Sơ đồ kiến thức</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if ($subject)
                    Bảng trắng tạo node và liên kết cho môn <span class="font-semibold" style="color: {{ $subject->color }}">{{ $subject->name }}</span>.
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
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($sharedUrl)
        <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-500/20 dark:bg-brand-500/10">
            <p class="text-sm font-semibold text-brand-800 dark:text-brand-300">Liên kết chia sẻ</p>
            <code class="mt-2 block select-all overflow-x-auto rounded-lg bg-white px-3 py-2 text-xs text-brand-900 dark:bg-night-900 dark:text-brand-200">{{ $sharedUrl }}</code>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($maps as $map)
            <div class="card flex flex-col" wire:key="map-{{ $map->id }}">
                <div class="flex items-start justify-between gap-2">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="map" class="h-5 w-5" />
                    </span>
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $map->visibility->label() }}</span>
                </div>

                <h2 class="mt-3 font-semibold text-slate-900 dark:text-white">{{ $map->title }}</h2>
                @if ($map->description)
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $map->description }}</p>
                @endif

                <p class="mt-2 text-xs text-slate-400">
                    {{ $map->owner?->name }} · v{{ $map->current_version }}
                    @if ($map->updated_at) · {{ $map->updated_at->diffForHumans() }} @endif
                </p>

                <div class="mt-4 flex flex-1 flex-wrap items-end gap-1.5">
                    <a href="{{ route('maps.edit', $map) }}" wire:navigate class="btn btn-primary px-3 py-1.5 text-xs">Mở</a>
                    @can('update', $map)
                        <button type="button" wire:click="openEdit({{ $map->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                    @endcan
                    @can('share', $map)
                        <button type="button" wire:click="share({{ $map->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Chia sẻ</button>
                    @endcan
                    <button type="button" wire:click="duplicate({{ $map->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Nhân bản</button>
                    @can('delete', $map)
                        <button type="button" wire:click="delete({{ $map->id }})" wire:confirm="Xóa sơ đồ {{ $map->title }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3 dark:text-slate-400">
                Chưa có sơ đồ nào. Hãy tạo sơ đồ đầu tiên.
            </div>
        @endforelse
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="w-full max-w-md rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingId ? 'Sửa sơ đồ' : 'Tạo sơ đồ' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="m-title">Tên sơ đồ</label>
                        <input id="m-title" type="text" class="input" wire:model="title" placeholder="Sơ đồ chuyên đề 1">
                        @error('title') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
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
                        <p class="mt-1 text-xs text-slate-400">{{ collect($visibilities)->firstWhere('value', $visibility)?->description() }}</p>
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
