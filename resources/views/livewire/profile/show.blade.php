<div class="space-y-6">
    @php
        $studentProfile = $user->studentProfile;
        $teacherProfile = $user->teacherProfile;
    @endphp

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <header class="flex items-center gap-4">
        <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-600 text-xl font-bold text-white">
            @if ($avatar)
                <img src="{{ $avatar->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
            @elseif ($user->avatar)
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                {{ $user->initials() }}
            @endif
        </span>
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $user->name }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <span class="badge bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $user->role->label() }}</span>
                @if ($user->subject)
                    <span class="badge" style="background-color: {{ $user->subject->color }}1a; color: {{ $user->subject->color }}">{{ $user->subject->name }}</span>
                @endif
                <span class="text-slate-400">{{ $user->email }}</span>
            </div>
        </div>
    </header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thông tin cá nhân</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="profile-name">Họ và tên</label>
                        <input id="profile-name" type="text" class="input" wire:model="name">
                        @error('name') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="profile-phone">Số điện thoại</label>
                        <input id="profile-phone" type="text" class="input" wire:model="phone">
                        @error('phone') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($user->isStudent())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="profile-class">Lớp</label>
                            <input id="profile-class" type="text" class="input" wire:model="className" placeholder="11A1">
                            @error('className') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="profile-dob">Ngày sinh</label>
                            <input id="profile-dob" type="date" class="input" wire:model="dateOfBirth">
                            @error('dateOfBirth') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                @if ($user->isTeacher())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="profile-code">Mã giáo viên</label>
                            <input id="profile-code" type="text" class="input" wire:model="employeeCode">
                        </div>
                        <div>
                            <label class="label" for="profile-bio">Giới thiệu</label>
                            <input id="profile-bio" type="text" class="input" wire:model="bio" placeholder="Giáo viên bồi dưỡng đội tuyển">
                        </div>
                    </div>
                @endif

                <div>
                    <label class="label" for="profile-avatar">Ảnh đại diện</label>
                    <input id="profile-avatar" type="file" class="input" wire:model="avatar" accept="image/*">
                    @error('avatar') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    <div class="mt-2 flex items-center gap-3">
                        <span wire:loading wire:target="avatar" class="text-xs text-slate-400">Đang tải ảnh…</span>
                        @if ($user->avatar)
                            <button type="button" wire:click="removeAvatar" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Xóa ảnh hiện tại</button>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Lưu hồ sơ</span>
                        <span wire:loading wire:target="save">Đang lưu…</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="card">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Cài đặt</h2>
                <div class="mt-4 space-y-2">
                    <button type="button" onclick="window.awawa.toggleTheme()" class="btn btn-outline w-full justify-start">
                        <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
                        <x-icon name="moon" class="h-5 w-5 dark:hidden" />
                        Đổi chế độ sáng / tối
                    </button>

                    <a href="{{ route('password.change') }}" wire:navigate class="btn btn-outline w-full justify-start">
                        <x-icon name="lock" class="h-5 w-5" />
                        Đổi mật khẩu
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost w-full justify-start">
                            <x-icon name="logout" class="h-5 w-5" />
                            Đăng xuất
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Thành tích</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Lịch sử điểm số, thành tích và biểu đồ tiến bộ sẽ xuất hiện ở đây khi kết quả thi được ghi nhận.
                </p>
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400 dark:bg-white/5">
                    Chưa có dữ liệu điểm
                </div>
            </div>
        </div>
    </div>
</div>
