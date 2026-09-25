<div class="space-y-5">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email" autofocus
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label class="label" for="password">Mật khẩu</label>
                <a href="{{ route('password.request') }}" wire:navigate
                    class="mb-1.5 text-[13px] font-medium text-brand-700 hover:underline dark:text-brand-300">Quên mật khẩu?</a>
            </div>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="current-password"
                placeholder="Mật khẩu của bạn">
            @error('password')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
            <input type="checkbox" wire:model="remember"
                class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
            Ghi nhớ đăng nhập
        </label>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Đăng nhập</span>
            <span wire:loading wire:target="login">Đang xử lý…</span>
        </button>
    </form>

    <div class="flex items-center gap-3 text-xs text-ink-faint dark:text-slate-500">
        <span class="h-px flex-1 bg-rule dark:bg-night-700"></span>
        hoặc
        <span class="h-px flex-1 bg-rule dark:bg-night-700"></span>
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline w-full">
        <x-google-icon class="h-5 w-5" />
        Tiếp tục với Google
    </a>

    <p class="text-center text-sm text-ink-soft dark:text-slate-400">
        Chưa có tài khoản?
        <a href="{{ route('register') }}" wire:navigate
            class="font-medium text-brand-700 hover:underline dark:text-brand-300">Tạo tài khoản</a>
    </p>
</div>
