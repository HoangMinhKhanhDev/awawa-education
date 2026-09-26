@php
    $aiReady = \App\Models\AiProvider::query()->where('is_enabled', true)->get()
        ->contains(fn ($provider) => filled($provider->api_key) && filled($provider->base_url));
    $tavilyReady = filled(\App\Support\NotebookConfig::tavilyKey());
    $streamOn = \App\Support\NotebookConfig::streamEnabled();
@endphp

<x-layouts.app title="API key">
    <div class="space-y-6" x-data="{ tab: '{{ $aiReady ? 'access' : 'ai' }}' }">
        <x-admin.tabs active="api-keys" />

        <div class="page-head">
            <div>
                <h1 class="page-title">API key & tích hợp</h1>
                <p class="page-sub">Kết nối dịch vụ AI, cấp khóa cho ứng dụng ngoài và tuỳ chọn Notebook.</p>
            </div>
        </div>

        {{-- Dải trạng thái --}}
        <div class="panel panel-pad">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] {{ $aiReady ? 'bg-success-soft text-success dark:bg-success/15 dark:text-emerald-300' : 'bg-warning-soft text-warning dark:bg-warning/15 dark:text-amber-300' }}">
                        <x-icon name="sparkles" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-ink dark:text-slate-100">Nhà cung cấp AI</p>
                        <p class="text-xs {{ $aiReady ? 'text-success' : 'text-warning' }}">{{ $aiReady ? 'Đã sẵn sàng' : 'Chưa cấu hình' }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] {{ $tavilyReady ? 'bg-success-soft text-success dark:bg-success/15 dark:text-emerald-300' : 'bg-paper-2 text-ink-faint dark:bg-white/5 dark:text-slate-500' }}">
                        <x-icon name="bolt" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-ink dark:text-slate-100">Tavily (tìm nguồn web)</p>
                        <p class="text-xs {{ $tavilyReady ? 'text-success' : 'text-ink-faint dark:text-slate-500' }}">{{ $tavilyReady ? 'Đã cấu hình' : 'Chưa cấu hình (tuỳ chọn)' }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] bg-paper-2 text-ink-faint dark:bg-white/5 dark:text-slate-500">
                        <x-icon name="chart" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-ink dark:text-slate-100">Streaming câu trả lời</p>
                        <p class="text-xs text-ink-faint dark:text-slate-500">{{ $streamOn ? 'Đang bật (SSE)' : 'Đã tắt' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab --}}
        <div class="tabs">
            <button type="button" @click="tab = 'ai'" class="tab" :class="tab === 'ai' && 'tab-active'">
                <x-icon name="sparkles" class="h-4 w-4" /> Nhà cung cấp AI
                @unless ($aiReady) <span class="ml-1 h-1.5 w-1.5 rounded-full bg-warning"></span> @endunless
            </button>
            <button type="button" @click="tab = 'access'" class="tab" :class="tab === 'access' && 'tab-active'">
                <x-icon name="key" class="h-4 w-4" /> Khóa truy cập ngoài
            </button>
            <button type="button" @click="tab = 'options'" class="tab" :class="tab === 'options' && 'tab-active'">
                <x-icon name="bolt" class="h-4 w-4" /> Tuỳ chọn & tích hợp
            </button>
        </div>

        <div x-show="tab === 'ai'"><livewire:admin.ai-providers.index /></div>
        <div x-show="tab === 'access'"><livewire:admin.api-keys.index /></div>
        <div x-show="tab === 'options'"><livewire:admin.integrations /></div>
    </div>
</x-layouts.app>
