<div class="space-y-6">
    <x-admin.tabs active="subjects" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Quản lý môn học</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Mỗi môn có bộ tính năng riêng. Bật/tắt tính năng tại đây để tách biệt hoàn toàn giữa các môn.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm môn
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @forelse ($subjects as $subject)
            <div class="card" wire:key="subject-{{ $subject->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $subject->color }}"></span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-slate-900 dark:text-white">{{ $subject->name }}</h2>
                                @if (! $subject->is_active)
                                    <span class="badge bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">Đang ẩn</span>
                                @endif
                            </div>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $subject->code }}</p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" wire:click="openEdit({{ $subject->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $subject->id }})"
                            wire:confirm="Xóa môn {{ $subject->name }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $subject->teachers_count }} giáo viên</span>
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $subject->students_count }} học sinh</span>
                    <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $subject->memberships_count }} thành viên đội</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($featureCases as $feature)
                        @if ($subject->hasFeature($feature))
                            <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $feature->label() }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 dark:text-slate-400">Chưa có môn học nào.</div>
        @endforelse
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingId ? 'Sửa môn học' : 'Thêm môn học' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="subject-name">Tên môn</label>
                            <input id="subject-name" type="text" class="input" wire:model.live.debounce.400ms="name">
                            @error('name') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="subject-code">Mã môn</label>
                            <input id="subject-code" type="text" class="input font-mono" wire:model="code" placeholder="toan">
                            @error('code') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="subject-color">Màu</label>
                            <input id="subject-color" type="color" class="input h-11 p-1" wire:model="color">
                            @error('color') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="subject-order">Thứ tự</label>
                            <input id="subject-order" type="number" class="input" wire:model="order" min="0">
                            @error('order') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="subject-description">Mô tả</label>
                        <textarea id="subject-description" class="input" rows="2" wire:model="description"></textarea>
                    </div>

                    <div>
                        <p class="label">Tính năng của môn</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($featureCases as $feature)
                                <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-white/10">
                                    <input type="checkbox" wire:model="features.{{ $feature->value }}"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                                    <span>
                                        <span class="block font-medium text-slate-700 dark:text-slate-200">{{ $feature->label() }}</span>
                                        <span class="block text-xs text-slate-400">{{ $feature->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                        Hiển thị môn này
                    </label>

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
