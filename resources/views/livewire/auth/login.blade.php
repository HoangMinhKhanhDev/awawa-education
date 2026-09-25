<div>
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email" autofocus
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label class="label" for="password">Mật khẩu</label>
                <a href="{{ route('password.request') }}" wire:navigate
                    class="mb-1.5 text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Quên mật khẩu?</a>
            </div>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="current-password"
                placeholder="••••••••">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model="remember"
                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
            Ghi nhớ đăng nhập
        </label>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Đăng nhập</span>
            <span wire:loading wire:target="login">Đang xử lý…</span>
        </button>
    </form>

    <div class="my-5 flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
        hoặc
        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline w-full">
        <x-google-icon class="h-5 w-5" />
        Tiếp tục với Google
    </a>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Chưa có tài khoản?
        <a href="{{ route('register') }}" wire:navigate
            class="font-semibold text-brand-600 hover:underline dark:text-brand-400">Đăng ký ngay</a>
    </p>
</div>
