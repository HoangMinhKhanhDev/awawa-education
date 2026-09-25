<div class="space-y-5">
    @if ($status)
        <div class="alert alert-success">{{ $status }}</div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="email" autofocus
                placeholder="ban@truong.edu.vn">
            @error('email')
                <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendResetLink">Gửi liên kết đặt lại</span>
            <span wire:loading wire:target="sendResetLink">Đang gửi…</span>
        </button>
    </form>

    <p class="text-center text-sm text-ink-soft dark:text-slate-400">
        <a href="{{ route('login') }}" wire:navigate
            class="font-medium text-brand-700 hover:underline dark:text-brand-300">Quay lại đăng nhập</a>
    </p>
</div>
