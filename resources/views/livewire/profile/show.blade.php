<div class="space-y-7">
    @php
        $studentProfile = $user->studentProfile;
        $teacherProfile = $user->teacherProfile;
    @endphp

    <div class="page-head">
        <div class="flex items-center gap-4">
            <div class="relative shrink-0">
                <label for="profile-avatar" title="Bấm để đổi ảnh đại diện"
                    class="flex h-14 w-14 cursor-pointer items-center justify-center overflow-hidden rounded-[14px] bg-brand-600 text-lg font-semibold text-white transition-opacity hover:opacity-90">
                    <span wire:loading.remove wire:target="avatar">
                        @if ($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                        @elseif ($user->avatar)
                            <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </span>
                    <span wire:loading wire:target="avatar" class="animate-pulse text-xs text-white/90">…</span>
                </label>
                <input id="profile-avatar" type="file" class="sr-only" wire:model="avatar" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="min-w-0">
                <h1 class="page-title">{{ $user->name }}</h1>
                <p class="page-sub">
                    {{ $user->role->label() }}@if ($user->subject)<span class="mx-1.5 text-rule-strong dark:text-night-700">/</span><span style="color: {{ $user->subject->color }}">{{ $user->subject->name }}</span>@endif
                    <span class="mx-1.5 text-rule-strong dark:text-night-700">/</span>{{ $user->email }}
                </p>
                <p class="mt-1 text-xs text-ink-faint dark:text-slate-500">
                    Bấm vào ảnh để đổi (JPG, PNG, WebP — tối đa 5MB)
                    @if ($user->avatar)
                        <span class="mx-1.5">·</span><button type="button" wire:click="removeAvatar" class="font-medium text-signal hover:underline dark:text-red-400">Xóa ảnh</button>
                    @endif
                </p>
                @error('avatar') <p class="mt-1 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
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
                        <div>
                            <label class="label" for="profile-note">Ghi chú về bản thân <span class="font-normal text-ink-faint dark:text-slate-500">(hiện cùng tên bạn ở bảng xếp hạng)</span></label>
                            <textarea id="profile-note" rows="2" maxlength="200" class="input" wire:model="note" placeholder="VD: Mục tiêu 9+ Toán, đang ôn bất đẳng thức"></textarea>
                            @error('note') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
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

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
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
<button type="button" data-theme-toggle onclick="window.awawa.cyclePreference()"
                        class="flex w-full items-center gap-3 px-5 py-3.5 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                        <x-icon name="sun" class="theme-icon theme-icon-light h-[18px] w-[18px]" />
                        <x-icon name="moon" class="theme-icon theme-icon-dark h-[18px] w-[18px]" />
                        <x-icon name="monitor" class="theme-icon theme-icon-system h-[18px] w-[18px]" />
                        <span class="theme-icon theme-icon-light">Giao diện: sáng</span>
                        <span class="theme-icon theme-icon-dark">Giao diện: tối</span>
                        <span class="theme-icon theme-icon-system">Giao diện: theo hệ thống</span>
                    </button>

                    <div class="relative px-5 py-3.5" x-data="{ accentOpen: false }" @click.outside="accentOpen = false" @keydown.escape.window="accentOpen = false">
                        @php
                            $currentPreset = \App\Enums\AccentColor::tryFrom($accent);
                            $isCustomAccent = $accent !== '' && $currentPreset === null;
                            $currentDot = $currentPreset ? $currentPreset->swatch() : ($isCustomAccent ? $accent : \App\Enums\AccentColor::Blue->swatch());
                            $currentLabel = $currentPreset ? $currentPreset->label() : ($isCustomAccent ? 'Tùy chỉnh' : 'Mặc định');
                        @endphp
                        <button type="button" @click="accentOpen = ! accentOpen"
                            class="flex w-full items-center gap-3 text-left text-sm text-ink-soft dark:text-slate-300"
                            :aria-expanded="accentOpen ? 'true' : 'false'" aria-label="Chọn màu điểm nhấn" aria-haspopup="listbox">
                            <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $currentDot }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">Màu điểm nhấn: <span class="font-medium text-ink dark:text-white">{{ $currentLabel }}</span></span>
                            <x-icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-ink-faint" />
                        </button>

                        <div x-show="accentOpen" x-cloak role="listbox" aria-label="Màu điểm nhấn"
                            class="panel absolute left-5 right-5 top-full z-40 -mt-1 space-y-0.5 p-2 shadow-lg">
                            <button type="button" wire:click="saveAccent('')" @click="accentOpen = false" role="option" aria-selected="{{ $accent === '' ? 'true' : 'false' }}"
                                class="flex w-full items-center gap-2.5 rounded-[8px] px-2.5 py-2 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                                <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ \App\Enums\AccentColor::Blue->swatch() }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">Mặc định</span>
                                @if ($accent === '')
                                    <x-icon name="check" class="h-4 w-4 shrink-0 text-ink dark:text-white" />
                                @endif
                            </button>
                            @foreach (\App\Enums\AccentColor::cases() as $color)
                                <button type="button" wire:click="saveAccent('{{ $color->value }}')" @click="accentOpen = false" role="option" aria-selected="{{ $accent === $color->value ? 'true' : 'false' }}"
                                    class="flex w-full items-center gap-2.5 rounded-[8px] px-2.5 py-2 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                                    wire:key="accent-{{ $color->value }}">
                                    <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $color->swatch() }}" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">{{ $color->label() }}</span>
                                    @if ($accent === $color->value)
                                        <x-icon name="check" class="h-4 w-4 shrink-0 text-ink dark:text-white" />
                                    @endif
                                </button>
                            @endforeach
                            <div class="border-t border-rule pt-1 dark:border-night-700">
                                <label class="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2.5 py-2 text-left text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5">
                                    <input type="color" value="{{ $isCustomAccent ? $accent : '#2154d6' }}" wire:change="saveAccent($event.target.value)"
                                        class="h-4 w-4 shrink-0 cursor-pointer appearance-none rounded-full border-0 bg-transparent p-0" aria-label="Chọn màu tùy chỉnh">
                                    <span class="min-w-0 flex-1">Tùy chỉnh</span>
                                    @if ($isCustomAccent)
                                        <x-icon name="check" class="h-4 w-4 shrink-0 text-ink dark:text-white" />
                                    @endif
                                </label>
                            </div>
                        </div>
                        @error('accent') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

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
