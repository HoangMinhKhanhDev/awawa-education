<div class="space-y-6">
    <x-admin.tabs active="subjects" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Quản lý môn học</h1>
            <p class="page-sub">Mỗi môn có bộ tính năng riêng. Bật hoặc tắt tại đây để tách biệt hoàn toàn giữa các môn.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm môn
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($subjects as $subject)
                <div class="flex flex-wrap items-start gap-4 px-5 py-4" wire:key="subject-{{ $subject->id }}">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $subject->color }}"></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium text-ink dark:text-slate-100">{{ $subject->name }}</h2>
                            @if (! $subject->is_active)
                                <span class="chip chip-signal">Đang ẩn</span>
                            @endif
                            <span class="font-mono text-xs text-ink-faint dark:text-slate-500">{{ $subject->code }}</span>
                        </div>

                        <p class="tnum mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                            <span>{{ $subject->teachers_count }} giáo viên</span>
                            <span>{{ $subject->students_count }} học sinh</span>
                            <span>{{ $subject->memberships_count }} thành viên đội</span>
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($featureCases as $feature)
                                @if ($subject->hasFeature($feature))
                                    <span class="chip chip-brand">{{ $feature->label() }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-1.5">
                        <button type="button" wire:click="openEdit({{ $subject->id }})" class="btn btn-outline px-3.5 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $subject->id }})" wire:confirm="Xóa môn {{ $subject->name }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có môn học nào.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa môn học' : 'Thêm môn học' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="subject-name">Tên môn</label>
                            <input id="subject-name" type="text" class="input" wire:model.live.debounce.400ms="name">
                            @error('name') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="subject-code">Mã môn</label>
                            <input id="subject-code" type="text" class="input font-mono" wire:model="code" placeholder="toan">
                            @error('code') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="subject-color">Màu</label>
                            <input id="subject-color" type="color" class="input h-11 p-1" wire:model="color">
                            @error('color') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="subject-order">Thứ tự</label>
                            <input id="subject-order" type="number" class="input tnum" wire:model="order" min="0">
                            @error('order') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
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
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-[10px] border border-rule p-3 text-sm dark:border-night-700">
                                    <input type="checkbox" wire:model="features.{{ $feature->value }}"
                                        class="mt-0.5 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                    <span>
                                        <span class="block font-medium text-ink dark:text-slate-200">{{ $feature->label() }}</span>
                                        <span class="block text-xs text-ink-faint dark:text-slate-500">{{ $feature->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        Hiển thị môn này
                    </label>

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
