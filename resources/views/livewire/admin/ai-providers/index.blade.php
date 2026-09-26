<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink dark:text-white">Nhà cung cấp AI</h2>
            <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Cấu hình API key cho các dịch vụ AI soạn đề và tài liệu (OpenRouter, Agnes AI...).</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="testConnection" class="btn btn-outline px-3.5 py-2 text-xs" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="testConnection">Kiểm tra kết nối</span>
                <span wire:loading wire:target="testConnection">Đang kiểm tra…</span>
            </button>
            <button type="button" wire:click="openCreate" class="btn btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Thêm nhà cung cấp
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="panel">
        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($providers as $provider)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="provider-{{ $provider->id }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-ink dark:text-slate-100">{{ $provider->label }}</p>
                            @if ($provider->is_default)
                                <span class="chip chip-brand">Mặc định</span>
                            @endif
                            @if (! $provider->is_enabled)
                                <span class="chip chip-neutral">Đang tắt</span>
                            @endif
                            <span class="font-mono text-xs text-ink-faint dark:text-slate-500">{{ $provider->key }}</span>
                        </div>
                        <dl class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-faint dark:text-slate-500">
                            <div class="flex gap-1.5"><dt>Endpoint</dt><dd class="text-ink-soft dark:text-slate-400">{{ $provider->base_url ?: '—' }}</dd></div>
                            <div class="flex gap-1.5"><dt>Model</dt><dd class="text-ink-soft dark:text-slate-400">{{ $provider->default_model ?: '—' }}</dd></div>
                            <div class="flex gap-1.5"><dt>API key</dt><dd>{{ $provider->hasCredentials() ? 'đã cấu hình' : 'chưa nhập' }}</dd></div>
                        </dl>
                    </div>

                    <span class="chip {{ $provider->hasCredentials() ? 'chip-success' : 'chip-warning' }}">
                        {{ $provider->hasCredentials() ? 'Sẵn sàng' : 'Thiếu key' }}
                    </span>

                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="openEdit({{ $provider->id }})" class="btn btn-outline px-3.5 py-1.5 text-xs">Sửa</button>
                        @if (! $provider->is_default)
                            <button type="button" wire:click="makeDefault({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Đặt mặc định</button>
                            <button type="button" wire:click="toggleEnabled({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">
                                {{ $provider->is_enabled ? 'Tắt' : 'Bật' }}
                            </button>
                            <button type="button" wire:click="delete({{ $provider->id }})" wire:confirm="Xóa nhà cung cấp {{ $provider->label }}?"
                                class="btn btn-ghost px-3 py-1.5 text-xs text-signal hover:bg-signal-soft dark:text-red-400 dark:hover:bg-red-500/10">Xóa</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="empty">Chưa có nhà cung cấp nào.</p>
            @endforelse
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="provider-key">Mã</label>
                            <input id="provider-key" type="text" class="input font-mono" wire:model="key" placeholder="openrouter">
                            @error('key') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="provider-label">Tên hiển thị</label>
                            <input id="provider-label" type="text" class="input" wire:model="label" placeholder="OpenRouter">
                            @error('label') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="provider-base">Base URL</label>
                        <input id="provider-base" type="url" class="input" wire:model="baseUrl" placeholder="https://openrouter.ai/api/v1">
                        @error('baseUrl') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="provider-apikey">API key {{ $editingId ? '(để trống nếu giữ nguyên)' : '' }}</label>
                        <input id="provider-apikey" type="password" class="input" wire:model="apiKey" autocomplete="off" placeholder="sk-...">
                        @error('apiKey') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="provider-model">Model mặc định</label>
                        <input id="provider-model" type="text" class="input" wire:model="defaultModel" placeholder="openrouter/free">
                    </div>

                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="isEnabled" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Kích hoạt nhà cung cấp
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="isDefault" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Đặt làm mặc định
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
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
