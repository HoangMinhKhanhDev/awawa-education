<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink dark:text-white">Khóa truy cập API</h2>
            <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Cấp khóa cho ứng dụng bên ngoài gọi vào awawa. Khóa chỉ hiện đầy đủ một lần khi tạo.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" />
            Tạo khóa
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($generatedKey)
        <div class="alert alert-warning">
            <p class="font-medium">Khóa mới, chỉ hiện một lần</p>
            <code class="mt-2 block select-all overflow-x-auto rounded-[10px] bg-white px-3 py-2 font-mono text-sm font-semibold text-ink dark:bg-night-900 dark:text-slate-100">{{ $generatedKey }}</code>
        </div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($keys as $key)
                @php
                    $statusClass = $key->isRevoked() ? 'chip-signal' : ($key->isExpired() ? 'chip-warning' : (! $key->is_active ? 'chip-neutral' : 'chip-success'));
                    $statusLabel = $key->isRevoked() ? 'Đã thu hồi' : ($key->isExpired() ? 'Hết hạn' : (! $key->is_active ? 'Tạm khóa' : 'Hoạt động'));
                @endphp
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="apikey-{{ $key->id }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-paper-2 text-ink-soft dark:bg-white/5 dark:text-slate-300">
                        <x-icon name="key" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $key->name }}</p>
                            <span class="chip {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-ink-faint dark:text-slate-500">{{ $key->maskedKey() }}</p>
                        <p class="tnum mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                            <span>{{ $key->rate_limit_per_minute }} req/phút</span>
                            <span>{{ count($key->scopes ?? []) }} quyền</span>
                            <span>{{ $key->last_used_at ? 'dùng lần cuối '.$key->last_used_at->diffForHumans() : 'chưa dùng' }}</span>
                            @if ($key->expires_at)<span>hết hạn {{ $key->expires_at->format('d/m/Y') }}</span>@endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" wire:click="toggleActive({{ $key->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $key->is_active ? 'Tạm khóa' : 'Kích hoạt' }}
                        </button>
                        @if (! $key->isRevoked())
                            <button type="button" wire:click="revoke({{ $key->id }})" wire:confirm="Thu hồi khóa {{ $key->name }}?"
                                class="btn btn-ghost px-3 py-1.5 text-xs text-warning hover:bg-warning-soft dark:text-amber-400 dark:hover:bg-amber-500/10">Thu hồi</button>
                        @endif
                        <button type="button" wire:click="delete({{ $key->id }})" wire:confirm="Xóa vĩnh viễn khóa {{ $key->name }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có khóa nào.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">Tạo khóa truy cập</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="create" class="space-y-4">
                    <div>
                        <label class="label" for="key-name">Tên khóa</label>
                        <input id="key-name" type="text" class="input" wire:model="name" placeholder="Ứng dụng điểm danh">
                        @error('name') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="key-rate">Giới hạn (req/phút)</label>
                            <input id="key-rate" type="number" class="input tnum" min="1" wire:model="rateLimitPerMinute">
                            @error('rateLimitPerMinute') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="key-expires">Ngày hết hạn</label>
                            <input id="key-expires" type="date" class="input" wire:model="expiresAt">
                            @error('expiresAt') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <p class="label">Quyền được cấp</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($scopeCases as $scope)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                                    <input type="checkbox" wire:model="scopes.{{ $scope->value }}"
                                        class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                    {{ $scope->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
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
