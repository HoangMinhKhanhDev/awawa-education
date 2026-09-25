<div class="space-y-6">
    <x-admin.tabs active="users" />

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Quản lý người dùng</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Tạo giáo viên và phân môn, quản lý học sinh và đội tuyển.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm người dùng
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

    @if ($generatedPassword)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Mật khẩu tạm thời (chỉ hiện một lần)</p>
            <code class="mt-2 block w-fit select-all rounded-lg bg-white px-3 py-1.5 text-base font-bold text-amber-900 dark:bg-night-900 dark:text-amber-200">{{ $generatedPassword }}</code>
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Sao chép và gửi cho người dùng. Họ sẽ phải đổi mật khẩu khi đăng nhập lần đầu.</p>
        </div>
    @endif

    {{-- Bộ lọc --}}
    <div class="card grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

    {{-- Danh sách --}}
    <div class="card overflow-hidden p-0">
        <div class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse ($users as $user)
                @php
                    $roleClasses = match ($user->role->value) {
                        'super_admin' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                        'teacher' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
                        default => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-3 p-4" wire:key="user-{{ $user->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-bold text-white">
                        @if ($user->avatar)
                            <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
                            <span class="badge {{ $roleClasses }}">{{ $user->role->label() }}</span>
                            @if ($user->subject)
                                <span class="badge" style="background-color: {{ $user->subject->color }}1a; color: {{ $user->subject->color }}">{{ $user->subject->name }}</span>
                            @endif
                            @if (! $user->is_active)
                                <span class="badge bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">Đã khóa</span>
                            @endif
                            @if ($user->isStudent() && $user->active_membership_count > 0)
                                <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Trong đội</span>
                            @endif
                        </div>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
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
                        <button type="button" wire:click="openEdit({{ $user->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                        <button type="button" wire:click="delete({{ $user->id }})"
                            wire:confirm="Xóa {{ $user->name }}? Hành động này không thể hoàn tác."
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Không tìm thấy người dùng nào.</div>
            @endforelse
        </div>
    </div>

    @if ($users->hasPages())
        <div>{{ $users->links() }}</div>
    @endif

    {{-- Form thêm/sửa --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ $editingId ? 'Sửa người dùng' : 'Thêm người dùng' }}
                    </h2>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="label" for="form-name">Họ và tên</label>
                        <input id="form-name" type="text" class="input" wire:model="name">
                        @error('name') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="form-email">Email</label>
                        <input id="form-email" type="email" class="input" wire:model="email">
                        @error('email') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="form-role">Vai trò</label>
                        <select id="form-role" class="input" wire:model.live="role">
                            @foreach ($roles as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($role !== 'super_admin')
                        <div>
                            <label class="label" for="form-subject">
                                Môn {{ $role === 'teacher' ? '(bắt buộc)' : '(không bắt buộc)' }}
                            </label>
                            <select id="form-subject" class="input" wire:model.live="subjectId">
                                <option value="">Chưa phân môn</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            @error('subjectId') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($role === 'student' && $subjectId)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="addToTeam" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            Thêm vào đội tuyển của môn này
                        </label>
                    @endif

                    <div>
                        <label class="label" for="form-password">
                            Mật khẩu {{ $editingId ? '(để trống nếu không đổi)' : '(để trống để hệ thống tự tạo)' }}
                        </label>
                        <input id="form-password" type="text" class="input" wire:model="password" autocomplete="off" placeholder="Tối thiểu 8 ký tự, có chữ và số">
                        @error('password') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                        Cho phép đăng nhập
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
