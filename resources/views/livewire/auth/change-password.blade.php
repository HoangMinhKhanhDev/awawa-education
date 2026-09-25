<div>
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (auth()->user()?->must_change_password)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Vì lý do bảo mật, bạn cần đổi mật khẩu trước khi tiếp tục.
        </div>
    @endif

    <form wire:submit="updatePassword" class="space-y-4">
        <div>
            <label class="label" for="current_password">Mật khẩu hiện tại</label>
            <input id="current_password" type="password" class="input" wire:model="current_password"
                autocomplete="current-password">
            @error('current_password')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">Mật khẩu mới</label>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password"
                placeholder="Tối thiểu 8 ký tự, có chữ và số">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">Nhập lại mật khẩu mới</label>
            <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation"
                autocomplete="new-password" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="updatePassword">Cập nhật mật khẩu</span>
            <span wire:loading wire:target="updatePassword">Đang cập nhật…</span>
        </button>
    </form>
</div>
