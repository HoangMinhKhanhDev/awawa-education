<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Quản lý học sinh</h1>
            <p class="page-sub">
                Thêm hoặc gỡ học sinh khỏi đội tuyển môn <span class="font-medium" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>. Học sinh chỉ làm bài sau khi vào đội.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if ($generatedPassword)
        <div class="alert alert-warning">
            <p class="font-medium">Mật khẩu tạm thời, chỉ hiện một lần</p>
            <code class="mt-2 block w-fit select-all rounded-[10px] bg-white px-3 py-1.5 font-mono text-base font-semibold text-ink dark:bg-night-900 dark:text-slate-100">{{ $generatedPassword }}</code>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="panel">
            <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thành viên đội tuyển</h2>
                <span class="tnum text-sm text-ink-faint dark:text-slate-500">{{ $members->count() }}</span>
            </div>

            <div class="divide-y divide-rule dark:divide-night-700">
                @forelse ($members as $membership)
                    <div class="flex items-center gap-3 px-5 py-3.5" wire:key="member-{{ $membership->id }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                            {{ $membership->student?->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink dark:text-slate-100">{{ $membership->student?->name }}</p>
                            <p class="truncate text-xs text-ink-faint dark:text-slate-500">{{ $membership->student?->email }}</p>
                        </div>
                        <button type="button" wire:click="removeStudent({{ $membership->student_id }})" wire:confirm="Gỡ học sinh khỏi đội?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Gỡ</button>
                    </div>
                @empty
                    <p class="empty">Đội tuyển chưa có học sinh nào.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thêm học sinh</h2>
                <button type="button" wire:click="openCreate" class="btn btn-outline px-3 py-1.5 text-xs">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Tạo tài khoản mới
                </button>
            </div>

            <div class="p-5">
                <input type="search" class="input" wire:model.live.debounce.400ms="search" placeholder="Tìm theo tên hoặc email">

                <div class="mt-3 divide-y divide-rule dark:divide-night-700">
                    @forelse ($candidates as $candidate)
                        <div class="flex items-center gap-3 py-3" wire:key="cand-{{ $candidate->id }}">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-paper-2 text-sm font-semibold text-ink-soft dark:bg-white/5 dark:text-slate-300">
                                {{ $candidate->initials() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink dark:text-slate-100">{{ $candidate->name }}</p>
                                <p class="truncate text-xs text-ink-faint dark:text-slate-500">{{ $candidate->email }}</p>
                            </div>
                            <button type="button" wire:click="addStudent({{ $candidate->id }})" class="btn btn-primary px-3.5 py-2 text-xs">Thêm</button>
                        </div>
                    @empty
                        <p class="empty">Không còn học sinh phù hợp. Tạo tài khoản mới hoặc để học sinh tự đăng ký.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($showCreate)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeCreate()">
            <div class="w-full max-w-md rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">Tạo tài khoản học sinh</h2>
                    <button type="button" wire:click="closeCreate" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="createStudent" class="space-y-4">
                    <div>
                        <label class="label" for="s-name">Họ và tên</label>
                        <input id="s-name" type="text" class="input" wire:model="newName">
                        @error('newName') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="s-email">Email</label>
                        <input id="s-email" type="email" class="input" wire:model="newEmail">
                        @error('newEmail') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-ink-faint dark:text-slate-500">Hệ thống tạo mật khẩu tạm và học sinh phải đổi khi đăng nhập lần đầu.</p>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeCreate" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="createStudent">Tạo và thêm vào đội</span>
                            <span wire:loading wire:target="createStudent">Đang tạo…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
