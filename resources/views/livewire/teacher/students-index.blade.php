<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Quản lý học sinh</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Thêm hoặc gỡ học sinh khỏi đội tuyển môn
            <span class="font-semibold" style="color: {{ $subject?->color }}">{{ $subject?->name }}</span>.
            Học sinh chỉ được làm bài sau khi vào đội.
        </p>
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

    @if ($generatedPassword)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Mật khẩu tạm thời (chỉ hiện một lần)</p>
            <code class="mt-2 block w-fit select-all rounded-lg bg-white px-3 py-1.5 text-base font-bold text-amber-900 dark:bg-night-900 dark:text-amber-200">{{ $generatedPassword }}</code>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thành viên đội tuyển</h2>
                <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $members->count() }}</span>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($members as $membership)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10" wire:key="member-{{ $membership->id }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                            {{ $membership->student?->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $membership->student?->name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $membership->student?->email }}</p>
                        </div>
                        <button type="button" wire:click="removeStudent({{ $membership->student_id }})" wire:confirm="Gỡ học sinh khỏi đội?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Gỡ</button>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Đội tuyển chưa có học sinh nào.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thêm học sinh</h2>
                <button type="button" wire:click="openCreate" class="btn btn-outline px-3 py-1.5 text-xs">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Tạo tài khoản mới
                </button>
            </div>

            <input type="search" class="input mt-4" wire:model.live.debounce.400ms="search" placeholder="Tìm theo tên hoặc email...">

            <div class="mt-3 space-y-2">
                @forelse ($candidates as $candidate)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10" wire:key="cand-{{ $candidate->id }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-500 dark:bg-white/10 dark:text-slate-300">
                            {{ $candidate->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $candidate->name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $candidate->email }}</p>
                        </div>
                        <button type="button" wire:click="addStudent({{ $candidate->id }})" class="btn btn-primary px-3 py-1.5 text-xs">Thêm</button>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">
                        Không còn học sinh nào phù hợp. Hãy tạo tài khoản mới hoặc học sinh tự đăng ký.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showCreate)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeCreate()">
            <div class="w-full max-w-md rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tạo tài khoản học sinh</h2>
                    <button type="button" wire:click="closeCreate" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="createStudent" class="space-y-4">
                    <div>
                        <label class="label" for="s-name">Họ và tên</label>
                        <input id="s-name" type="text" class="input" wire:model="newName">
                        @error('newName') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="s-email">Email</label>
                        <input id="s-email" type="email" class="input" wire:model="newEmail">
                        @error('newEmail') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-slate-400">Hệ thống sẽ tạo mật khẩu tạm và học sinh phải đổi khi đăng nhập lần đầu.</p>
                    <div class="flex justify-end gap-2 pt-2">
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
