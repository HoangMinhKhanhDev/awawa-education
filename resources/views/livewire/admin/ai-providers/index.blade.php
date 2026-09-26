<div class="space-y-5">
    <div>
        <h2 class="text-base font-semibold text-ink dark:text-white">Nhà cung cấp AI</h2>
        <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Cần ít nhất một nhà cung cấp để Notebook hoạt động. Hỗ trợ mọi dịch vụ chuẩn OpenAI.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @unless ($ready)
        <div class="alert alert-warning">Chưa có nhà cung cấp AI nào sẵn sàng. Chọn dịch vụ và dán API key bên dưới.</div>
    @endunless

    @php $activePreset = $presets->firstWhere('preset', $quickPreset); @endphp

    {{-- Thêm nhanh --}}
    <div class="panel panel-pad space-y-3">
        <div class="grid gap-2 sm:grid-cols-[minmax(0,200px)_minmax(0,1fr)_auto]">
            <select class="input" wire:model.live="quickPreset" aria-label="Chọn nhà cung cấp">
                @foreach ($presets as $preset)
                    <option value="{{ $preset['preset'] }}">{{ $preset['label'] }}{{ $preset['configured'] ? ' ✓' : '' }}</option>
                @endforeach
            </select>

            <input type="password" class="input" wire:model="quickKey" autocomplete="off"
                wire:keydown.enter.prevent="quickSave" placeholder="Dán API key…">

            <button type="button" wire:click="quickSave" class="btn btn-primary shrink-0" wire:loading.attr="disabled" wire:target="quickSave">
                <span wire:loading.remove wire:target="quickSave">Lưu &amp; kiểm tra</span>
                <span wire:loading wire:target="quickSave">Đang kiểm tra…</span>
            </button>
        </div>

        @error('quickKey') <p class="text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror

        @if ($activePreset)
            <p class="text-xs text-ink-faint dark:text-slate-500">
                {{ $activePreset['hint'] }}
                @if ($activePreset['docs'])
                    <a href="{{ $activePreset['docs'] }}" target="_blank" rel="noopener" class="ml-1 font-medium text-brand-700 hover:underline dark:text-brand-300">Lấy API key</a>
                @endif
                <span class="mx-1">·</span>Endpoint tự đặt theo dịch vụ.
            </p>
        @endif
    </div>

    {{-- Danh sách --}}
    <div class="panel">
        <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
            <h3 class="text-sm font-semibold text-ink dark:text-white">Đã cấu hình ({{ $providers->count() }})</h3>
            <button type="button" wire:click="openCreate" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Thêm thủ công</button>
        </div>

        <div class="divide-y divide-rule dark:divide-night-700">
            @forelse ($providers as $provider)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="provider-{{ $provider->id }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-ink dark:text-slate-100">{{ $provider->label }}</span>
                            <span class="chip {{ $provider->hasCredentials() ? 'chip-success' : 'chip-warning' }}">{{ $provider->hasCredentials() ? 'Sẵn sàng' : 'Thiếu key' }}</span>
                            @if ($provider->is_default)<span class="chip chip-brand">Mặc định</span>@endif
                            @if (! $provider->is_enabled)<span class="chip chip-neutral">Đang tắt</span>@endif
                        </div>
                        <p class="mt-0.5 truncate text-xs text-ink-faint dark:text-slate-500">{{ $provider->base_url ?: '—' }} · {{ $provider->default_model ?: 'chưa chọn model' }}</p>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="checkProvider({{ $provider->id }})" class="btn btn-outline px-3 py-1.5 text-xs"
                            wire:loading.attr="disabled" wire:target="checkProvider({{ $provider->id }})">Kiểm tra</button>
                        <button type="button" wire:click="openEdit({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Sửa</button>
                        @if (! $provider->is_default)
                            <button type="button" wire:click="makeDefault({{ $provider->id }})" class="btn btn-ghost px-3 py-1.5 text-xs">Đặt mặc định</button>
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

    {{-- Form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800" @click.stop>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">{{ $editingId ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp' }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                @unless ($editingId)
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        @foreach ($presets as $preset)
                            <button type="button" wire:click="usePreset('{{ $preset['preset'] }}')"
                                class="rounded-full border border-rule px-3 py-1.5 text-xs text-ink-soft hover:bg-paper-2 dark:border-night-700 dark:text-slate-300 dark:hover:bg-white/5">
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endunless

                <form wire:submit="save" class="space-y-4">
                    @if ($baseUrl === '')
                        <div>
                            <label class="label" for="provider-base">Endpoint (Base URL)</label>
                            <input id="provider-base" type="url" class="input" wire:model="baseUrl" placeholder="https://...">
                            <p class="mt-1.5 text-xs text-ink-faint dark:text-slate-500">Chỉ nhập khi dùng dịch vụ không có trong danh sách.</p>
                            @error('baseUrl') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <span class="label">Endpoint</span>
                            <div class="flex items-center justify-between gap-2 rounded-[10px] border border-rule bg-paper-2 px-3.5 py-2.5 dark:border-night-700 dark:bg-night-900/40">
                                <span class="truncate font-mono text-xs text-ink-soft dark:text-slate-400">{{ $baseUrl }}</span>
                                <button type="button" wire:click="$set('baseUrl', '')" class="shrink-0 text-xs text-ink-faint hover:text-brand-700 dark:text-slate-500">Đổi</button>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="label" for="provider-apikey">API key {{ $editingId ? '(để trống nếu giữ nguyên)' : '' }}</label>
                        <input id="provider-apikey" type="password" class="input" wire:model="apiKey" autocomplete="off" placeholder="sk-...">
                        @error('apiKey') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-end justify-between gap-2">
                            <label class="label mb-1.5" for="provider-model">Model mặc định</label>
                            <button type="button" wire:click="fetchFormModels" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300"
                                wire:loading.attr="disabled" wire:target="fetchFormModels">
                                <span wire:loading.remove wire:target="fetchFormModels">Tải danh sách model</span>
                                <span wire:loading wire:target="fetchFormModels">Đang tải…</span>
                            </button>
                        </div>
                        @if ($modelList !== [])
                            <select id="provider-model" class="input" wire:model="defaultModel">
                                <option value="">— Chọn model —</option>
                                @foreach ($modelList as $model)
                                    <option value="{{ $model['id'] }}">{{ $model['id'] }}{{ $model['free'] ? ' · miễn phí' : '' }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="provider-model" type="text" class="input" wire:model="defaultModel" placeholder="Bấm “Tải danh sách model” để chọn">
                        @endif
                        @if ($probeMessage)
                            <p class="mt-1.5 text-xs text-success">{{ $probeMessage }}</p>
                        @endif
                    </div>

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

                    <div class="space-y-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                            <input type="checkbox" wire:model="isEnabled" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                            Kích hoạt
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
