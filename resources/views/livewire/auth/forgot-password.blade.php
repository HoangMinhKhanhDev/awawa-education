<div>
    @if ($status)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email" autofocus
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendResetLink">Gửi liên kết đặt lại</span>
            <span wire:loading wire:target="sendResetLink">Đang gửi…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('login') }}" wire:navigate
            class="font-semibold text-brand-600 hover:underline dark:text-brand-400">Quay lại đăng nhập</a>
    </p>
</div>
