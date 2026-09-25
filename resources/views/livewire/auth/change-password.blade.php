<div class="space-y-5">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (auth()->user()?->must_change_password)
        <div class="alert alert-warning">
            Vì lý do bảo mật, bạn cần đổi mật khẩu trước khi tiếp tục.
        </div>
    @endif

    <form wire:submit="updatePassword" class="space-y-4">
        <div>
            <label class="label" for="current_password">Mật khẩu hiện tại</label>
            <input id="current_password" type="password" class="input" wire:model="current_password"
                autocomplete="current-password">
            @error('current_password')
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
            <span wire:loading.remove wire:target="updatePassword">Cập nhật mật khẩu</span>
            <span wire:loading wire:target="updatePassword">Đang cập nhật…</span>
        </button>
    </form>
</div>
