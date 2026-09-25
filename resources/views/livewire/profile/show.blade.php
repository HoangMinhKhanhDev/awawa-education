<div class="space-y-7">
    @php
        $studentProfile = $user->studentProfile;
        $teacherProfile = $user->teacherProfile;
    @endphp

    <div class="page-head">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[14px] bg-brand-600 text-lg font-semibold text-white">
                @if ($avatar)
                    <img src="{{ $avatar->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                @elseif ($user->avatar)
                    <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ $user->initials() }}
                @endif
            </span>
            <div>
                <h1 class="page-title">{{ $user->name }}</h1>
                <p class="page-sub">
                    {{ $user->role->label() }}@if ($user->subject)<span class="mx-1.5 text-rule-strong dark:text-night-700">/</span><span style="color: {{ $user->subject->color }}">{{ $user->subject->name }}</span>@endif
                    <span class="mx-1.5 text-rule-strong dark:text-night-700">/</span>{{ $user->email }}
                </p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thông tin cá nhân</h2>
                </div>

                <form wire:submit="save" class="space-y-4 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="profile-name">Họ và tên</label>
                            <input id="profile-name" type="text" class="input" wire:model="name">
                            @error('name') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="profile-phone">Số điện thoại</label>
                            <input id="profile-phone" type="text" class="input" wire:model="phone">
                            @error('phone') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($user->isStudent())
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="profile-class">Lớp</label>
                                <input id="profile-class" type="text" class="input" wire:model="className" placeholder="11A1">
                                @error('className') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="profile-dob">Ngày sinh</label>
                                <input id="profile-dob" type="date" class="input" wire:model="dateOfBirth">
                                @error('dateOfBirth') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
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
                        @error('avatar') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        <div class="mt-2 flex items-center gap-4">
                            <span wire:loading wire:target="avatar" class="text-xs text-ink-faint dark:text-slate-500">Đang tải ảnh…</span>
                            @if ($user->avatar)
                                <button type="button" wire:click="removeAvatar" class="text-xs font-medium text-signal hover:underline dark:text-red-400">Xóa ảnh hiện tại</button>
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
        </div>

        <div class="space-y-5">
            <div class="panel">
                <div class="border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Cài đặt</h2>
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    <button type="button" onclick="window.awawa.toggleTheme()"
                        class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                        <x-icon name="sun" class="hidden h-[18px] w-[18px] dark:block" />
                        <x-icon name="moon" class="h-[18px] w-[18px] dark:hidden" />
                        Đổi chế độ sáng / tối
                    </button>

                    <div x-data="{ push: 'idle', init() { if (window.AwawaPush) { window.AwawaPush.status().then((s) => { this.push = s; }); } } }">
                        <button type="button"
                            class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                            @click="if (push === 'enabled') { window.AwawaPush.disable().then(() => { push = 'disabled'; }); } else { window.AwawaPush.enable().then((r) => { push = r.ok ? 'enabled' : (r.reason || 'error'); }); }">
                            <x-icon name="bell" class="h-[18px] w-[18px]" />
                            <span x-show="push !== 'enabled'">Bật thông báo đẩy</span>
                            <span x-show="push === 'enabled'">Tắt thông báo đẩy</span>
                        </button>
                        <p x-show="push === 'denied'" x-cloak class="px-5 pb-3 text-xs text-warning dark:text-amber-400">Trình duyệt đã chặn quyền thông báo.</p>
                        <p x-show="push === 'not-configured'" x-cloak class="px-5 pb-3 text-xs text-ink-faint dark:text-slate-500">Máy chủ chưa cấu hình Web Push.</p>
                        <p x-show="push === 'unsupported'" x-cloak class="px-5 pb-3 text-xs text-ink-faint dark:text-slate-500">Thiết bị hoặc trình duyệt không hỗ trợ.</p>
                    </div>

                    <a href="{{ route('password.change') }}" wire:navigate
                        class="flex items-center gap-3 px-5 py-3.5 text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                        <x-icon name="lock" class="h-[18px] w-[18px]" />
                        Đổi mật khẩu
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                            <x-icon name="logout" class="h-[18px] w-[18px]" />
                            Đăng xuất
                        </button>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                    <h2 class="text-[15px] font-semibold text-ink dark:text-white">Thành tích</h2>
                    @if (($totalScore ?? 0) > 0)
                        <span class="tnum text-sm text-ink-soft dark:text-slate-400">{{ $totalScore }} điểm</span>
                    @endif
                </div>
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($history as $attempt)
                        <a href="{{ route('student.result', $attempt->exam) }}" wire:navigate
                            class="flex items-center justify-between gap-3 px-5 py-3 text-sm transition-colors hover:bg-paper-2 dark:hover:bg-white/5">
                            <span class="min-w-0 truncate text-ink dark:text-slate-200">{{ $attempt->exam?->title }}</span>
                            <span class="tnum shrink-0 font-medium text-brand-700 dark:text-brand-300">{{ (float) $attempt->score }}/{{ (float) $attempt->max_score }}</span>
                        </a>
                    @empty
                        <p class="empty">Chưa có dữ liệu điểm.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
