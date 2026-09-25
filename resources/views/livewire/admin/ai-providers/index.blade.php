<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Nhà cung cấp AI</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Cấu hình API key cho các dịch vụ AI tạo đề/tài liệu (OpenRouter, Agnes AI, ...).
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-outline">
            <x-icon name="plus" class="h-4 w-4" />
            Thêm nhà cung cấp
        </button>
    </header>

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @forelse ($providers as $provider)
            <div class="card" wire:key="provider-{{ $provider->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $provider->label }}</h3>
                            @if ($provider->is_default)
                                <span class="badge bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Mặc định</span>
                            @endif
                            @if (! $provider->is_enabled)
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">Đang tắt</span>
                            @endif
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $provider->key }}</p>
                    </div>
                    <span class="badge {{ $provider->hasCredentials() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">
                        {{ $provider->hasCredentials() ? 'Đã cấu hình' : 'Thiếu key' }}
                    </span>
                </div>

                <dl class="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex gap-2"><dt class="w-20 shrink-0">Endpoint</dt><dd class="truncate">{{ $provider->base_url ?: '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-20 shrink-0">Model</dt><dd class="truncate">{{ $provider->default_model ?: '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-20 shrink-0">API key</dt><dd>{{ $provider->hasCredentials() ? '••••••••••••' : 'chưa nhập' }}</dd></div>
                </dl>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <button type="button" wire:click="openEdit({{ $provider->id }})" class="btn btn-outline px-3 py-1.5 text-xs">Sửa</button>
                    @if (! $provider->is_default)
                        <button type="button" wire:click="makeDefault({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Đặt mặc định</button>
                        <button type="button" wire:click="toggleEnabled({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                            {{ $provider->is_enabled ? 'Tắt' : 'Bật' }}
                        </button>
                        <button type="button" wire:click="delete({{ $provider->id }})" wire:confirm="Xóa nhà cung cấp {{ $provider->label }}?"
                            class="btn btn-ghost px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="card text-center text-sm text-slate-500 dark:text-slate-400">Chưa có nhà cung cấp nào.</div>
        @endforelse
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingId ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp' }}</h3>
                    <button type="button" wire:click="closeForm" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="provider-key">Mã</label>
                            <input id="provider-key" type="text" class="input font-mono" wire:model="key" placeholder="openrouter">
                            @error('key') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="provider-label">Tên hiển thị</label>
                            <input id="provider-label" type="text" class="input" wire:model="label" placeholder="OpenRouter">
                            @error('label') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="provider-base">Base URL</label>
                        <input id="provider-base" type="url" class="input" wire:model="baseUrl" placeholder="https://openrouter.ai/api/v1">
                        @error('baseUrl') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="provider-apikey">
                            API key {{ $editingId ? '(để trống nếu giữ nguyên)' : '' }}
                        </label>
                        <input id="provider-apikey" type="password" class="input" wire:model="apiKey" autocomplete="off" placeholder="sk-...">
                        @error('apiKey') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="provider-model">Model mặc định</label>
                        <input id="provider-model" type="text" class="input" wire:model="defaultModel" placeholder="openrouter/free">
                    </div>

                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="isEnabled" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            Kích hoạt nhà cung cấp
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" wire:model="isDefault" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20">
                            Đặt làm mặc định
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Lưu</span>
                            <span wire:loading wire:target="save">Đang lưu…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
