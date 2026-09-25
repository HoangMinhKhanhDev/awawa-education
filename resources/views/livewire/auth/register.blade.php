<div class="space-y-5">
    <div class="alert alert-warning">
        Tài khoản mới mặc định là <strong>học sinh</strong> và chỉ xem được tài liệu công khai. Khi được giáo viên thêm vào đội tuyển, bạn mới làm bài và tính điểm.
    </div>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="label" for="name">Họ và tên</label>
            <input id="name" type="text" class="input" wire:model="name" autocomplete="name" autofocus
                placeholder="Nguyễn Văn A">
            @error('name')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email"
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">Mật khẩu</label>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password"
                placeholder="Tối thiểu 8 ký tự, có chữ và số">
            @error('password')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation"
                autocomplete="new-password" placeholder="Nhập lại mật khẩu">
        </div>

        <label class="flex cursor-pointer items-start gap-2 text-sm text-ink-soft dark:text-slate-300">
            <input type="checkbox" wire:model="terms"
                class="mt-0.5 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
            <span>Tôi đồng ý tuân thủ nội quy sử dụng của đội tuyển.</span>
        </label>
        @error('terms')
            <p class="text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="register">Tạo tài khoản</span>
            <span wire:loading wire:target="register">Đang tạo…</span>
        </button>
    </form>

    <div class="flex items-center gap-3 text-xs text-ink-faint dark:text-slate-500">
        <span class="h-px flex-1 bg-rule dark:bg-night-700"></span>
        hoặc
        <span class="h-px flex-1 bg-rule dark:bg-night-700"></span>
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline w-full">
        <x-google-icon class="h-5 w-5" />
        Đăng ký bằng Google
    </a>

    <p class="text-center text-sm text-ink-soft dark:text-slate-400">
        Đã có tài khoản?
        <a href="{{ route('login') }}" wire:navigate
            class="font-medium text-brand-700 hover:underline dark:text-brand-300">Đăng nhập</a>
    </p>
</div>
