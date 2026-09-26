<div class="space-y-6">
    <x-admin.tabs active="users" />

    <div class="page-head">
        <div>
            <h1 class="page-title">Quản lý người dùng</h1>
            <p class="page-sub">Tạo giáo viên và phân môn, quản lý học sinh và đội tuyển.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm người dùng
        </button>
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
            <p class="mt-1.5 text-xs">Sao chép và gửi cho người dùng. Họ phải đổi mật khẩu khi đăng nhập lần đầu.</p>
        </div>
    @endif

    <div class="panel panel-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="label" for="search">Tìm kiếm</label>
            <input id="search" type="search" class="input" placeholder="Tên hoặc email" wire:model.live.debounce.400ms="search">
        </div>
        <div>
            <label class="label" for="roleFilter">Vai trò</label>
            <select id="roleFilter" class="input" wire:model.live="roleFilter">
                <option value="">Tất cả</option>
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="subjectFilter">Môn</label>
            <select id="subjectFilter" class="input" wire:model.live="subjectFilter">
                <option value="">Tất cả</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="statusFilter">Trạng thái</label>
            <select id="statusFilter" class="input" wire:model.live="statusFilter">
                <option value="">Tất cả</option>
                <option value="active">Đang hoạt động</option>
                <option value="inactive">Đã khóa</option>
            </select>
        </div>
    </div>

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($users as $user)
                @php
                    $roleClass = match ($user->role->value) {
                        'super_admin' => 'chip-brand',
                        'teacher' => 'chip-neutral',
                        default => 'chip-success',
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="user-{{ $user->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                        @if ($user->avatar)
                            <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-ink dark:text-slate-100">{{ $user->name }}</p>
                            <span class="chip {{ $roleClass }}">{{ $user->role->label() }}</span>
                            @if ($user->subject)
                                <span class="chip chip-neutral" style="color: {{ $user->subject->color }}">{{ $user->subject->name }}</span>
                            @endif
                            @if (! $user->is_active)
                                <span class="chip chip-signal">Đã khóa</span>
                            @endif
                            @if ($user->isStudent() && $user->active_membership_count > 0)
                                <span class="chip chip-success">Trong đội</span>
                            @endif
                        </div>
                        <p class="truncate text-xs text-ink-faint dark:text-slate-500">{{ $user->email }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($user->isStudent() && $user->subject_id)
                            <button type="button" wire:click="toggleTeam({{ $user->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                                {{ $user->active_membership_count > 0 ? 'Gỡ khỏi đội' : 'Thêm vào đội' }}
                            </button>
                        @endif
                        <button type="button" wire:click="resetPassword({{ $user->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Đặt lại MK</button>
                        <button type="button" wire:click="toggleActive({{ $user->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $user->is_active ? 'Khóa' : 'Mở khóa' }}
                        </button>
                        <button type="button" wire:click="openEdit({{ $user->id }})" class="btn btn-outline px-3.5 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $user->id }})"
                            wire:confirm="Xóa {{ $user->name }}? Hành động này không thể hoàn tác."
                            class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <p class="empty">Không tìm thấy người dùng nào.</p>
            @endforelse
        </div>
    </div>

    @if ($users->hasPages())
        <div>{{ $users->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa người dùng' : 'Thêm người dùng' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="form-name">Họ và tên</label>
                        <input id="form-name" type="text" class="input" wire:model="name">
                        @error('name') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="form-email">Email</label>
                        <input id="form-email" type="email" class="input" wire:model="email">
                        @error('email') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="form-role">Vai trò</label>
                        <select id="form-role" class="input" wire:model.live="role">
                            @foreach ($roles as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($role !== 'super_admin')
                        <div>
                            <label class="label" for="form-subject">Môn {{ $role === 'teacher' ? '(bắt buộc)' : '(không bắt buộc)' }}</label>
                            <select id="form-subject" class="input" wire:model.live="subjectId">
                                <option value="">Chưa phân môn</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            @error('subjectId') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($role === 'student' && $subjectId)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="addToTeam" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Thêm vào đội tuyển của môn này
                        </label>
                    @endif

                    <div>
                        <label class="label" for="form-password">Mật khẩu {{ $editingId ? '(để trống nếu không đổi)' : '(để trống để hệ thống tự tạo)' }}</label>
                        <input id="form-password" type="text" class="input" wire:model="password" autocomplete="off" placeholder="Tối thiểu 8 ký tự, có chữ và số">
                        @error('password') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                        Cho phép đăng nhập
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
