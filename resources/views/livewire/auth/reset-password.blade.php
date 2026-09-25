<form wire:submit="resetPassword" class="space-y-4">
    <div>
        <label class="label" for="email">Email</label>
        <input id="email" type="email" class="input" wire:model="email" autocomplete="email">
        @error('email')
            <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="password">Mật khẩu mới</label>
        <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password"
            placeholder="Tối thiểu 8 ký tự, có chữ và số">
        @error('password')
            <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="label" for="password_confirmation">Nhập lại mật khẩu mới</label>
        <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation"
            autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
    </div>

    <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="resetPassword">Đặt lại mật khẩu</span>
        <span wire:loading wire:target="resetPassword">Đang xử lý…</span>
    </button>
</form>
