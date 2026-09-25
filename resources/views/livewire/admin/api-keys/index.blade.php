<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Khóa truy cập API</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Cấp khóa cho ứng dụng bên ngoài gọi vào awawa. Khóa chỉ hiển thị đầy đủ một lần khi tạo.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo khóa
        </button>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($generatedKey)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Khóa mới (chỉ hiện một lần)</p>
            <code class="mt-2 block select-all overflow-x-auto rounded-lg bg-white px-3 py-2 font-mono text-sm font-semibold text-amber-900 dark:bg-night-900 dark:text-amber-200">{{ $generatedKey }}</code>
        </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="divide-y divide-slate-100 dark:divide-white/5">
            @forelse ($keys as $key)
                <div class="flex flex-wrap items-center gap-3 p-4" wire:key="apikey-{{ $key->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="key" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $key->name }}</p>
                            @if ($key->isRevoked())
                                <span class="badge bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">Đã thu hồi</span>
                            @elseif ($key->isExpired())
                                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Hết hạn</span>
                            @elseif (! $key->is_active)
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">Tạm khóa</span>
                            @else
                                <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Hoạt động</span>
                            @endif
                        </div>
                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $key->maskedKey() }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $key->rate_limit_per_minute }} req/phút
                            · {{ count($key->scopes ?? []) }} quyền
                            · {{ $key->last_used_at ? 'dùng lần cuối '.$key->last_used_at->diffForHumans() : 'chưa dùng' }}
                            @if ($key->expires_at)
                                · hết hạn {{ $key->expires_at->format('d/m/Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" wire:click="toggleActive({{ $key->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $key->is_active ? 'Tạm khóa' : 'Kích hoạt' }}
                        </button>
                        @if (! $key->isRevoked())
                            <button type="button" wire:click="revoke({{ $key->id }})" wire:confirm="Thu hồi khóa {{ $key->name }}?"
                                class="btn btn-ghost px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10">Thu hồi</button>
                        @endif
                        <button type="button" wire:click="delete({{ $key->id }})" wire:confirm="Xóa vĩnh viễn khóa {{ $key->name }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Chưa có khóa nào.</div>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Tạo khóa truy cập</h3>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="create" class="space-y-4">
                    <div>
                        <label class="label" for="key-name">Tên khóa</label>
                        <input id="key-name" type="text" class="input" wire:model="name" placeholder="Ứng dụng điểm danh">
                        @error('name') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="key-rate">Giới hạn (req/phút)</label>
                            <input id="key-rate" type="number" class="input" min="1" wire:model="rateLimitPerMinute">
                            @error('rateLimitPerMinute') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="key-expires">Ngày hết hạn</label>
                            <input id="key-expires" type="date" class="input" wire:model="expiresAt">
                            @error('expiresAt') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <p class="label">Quyền được cấp</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($scopeCases as $scope)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" wire:model="scopes.{{ $scope->value }}"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                                    {{ $scope->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="create">Tạo khóa</span>
                            <span wire:loading wire:target="create">Đang tạo…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
