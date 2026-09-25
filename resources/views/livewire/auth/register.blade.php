<div>
    <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300">
        Tài khoản mới mặc định là <strong>học sinh</strong> và chỉ xem được tài liệu công khai. Khi giáo viên thêm bạn vào đội tuyển, bạn mới có thể làm bài và tính điểm.
    </div>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="label" for="name">Họ và tên</label>
            <input id="name" type="text" class="input" wire:model="name" autocomplete="name" autofocus
                placeholder="Nguyễn Văn A">
            @error('name')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email"
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">Mật khẩu</label>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password"
                placeholder="Tối thiểu 8 ký tự, có chữ và số">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation"
                autocomplete="new-password" placeholder="••••••••">
        </div>

        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model="terms"
                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
            <span>Tôi đồng ý tuân thủ nội quy sử dụng của đội tuyển.</span>
        </label>
        @error('terms')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="register">Tạo tài khoản</span>
            <span wire:loading wire:target="register">Đang tạo…</span>
        </button>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
        hoặc
        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline w-full">
        <x-google-icon class="h-5 w-5" />
        Đăng ký bằng Google
    </a>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Đã có tài khoản?
        <a href="{{ route('login') }}" wire:navigate
            class="font-semibold text-brand-600 hover:underline dark:text-brand-400">Đăng nhập</a>
    </p>
</div>
