<div class="space-y-5">
    <div>
        <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">Tích hợp & tuỳ chọn</h2>
        <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Cấu hình dịch vụ ngoài và hành vi của Notebook (AI Studio).</p>
    </div>

    @if (session('integrations_status'))
        <div class="alert alert-success">{{ session('integrations_status') }}</div>
    @endif

    @if (session('integrations_error'))
        <div class="alert alert-error">{{ session('integrations_error') }}</div>
    @endif

    <div class="panel panel-pad space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-semibold text-ink dark:text-white">Tavily (tìm nguồn web)</h3>
                <p class="mt-0.5 text-sm text-ink-soft dark:text-slate-400">Dùng cho tính năng “Tìm trên web” trong Notebook.</p>
            </div>
            <span class="chip {{ $tavilyConfigured ? 'chip-success' : 'chip-neutral' }}">{{ $tavilyConfigured ? 'Đã cấu hình' : 'Chưa cấu hình' }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="label" for="int-tavily">Tavily API key (để trống nếu giữ nguyên)</label>
                <input id="int-tavily" type="password" class="input" wire:model="tavilyApiKey" autocomplete="off" placeholder="tvly-...">
                @error('tavilyApiKey') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="int-max">Giới hạn ký tự nguồn mỗi lần gọi AI</label>
                <input id="int-max" type="number" class="input tnum" min="10000" max="2000000" step="10000" wire:model="maxPromptChars">
                @error('maxPromptChars') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="int-max-sources">Số nguồn tối đa mỗi Notebook</label>
                <input id="int-max-sources" type="number" class="input tnum" min="1" max="100" wire:model="maxSources">
                @error('maxSources') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="int-max-file">Dung lượng tệp tối đa (MB)</label>
                <input id="int-max-file" type="number" class="input tnum" min="1" max="100" wire:model="maxFileMegabytes">
                @error('maxFileMegabytes') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="int-max-source-chars">Ký tự tối đa trong mỗi nguồn</label>
                <input id="int-max-source-chars" type="number" class="input tnum" min="1000" max="2000000" step="1000" wire:model="maxSourceChars">
                @error('maxSourceChars') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
            <input type="checkbox" wire:model="aiStream" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
            Bật streaming (SSE) cho câu trả lời — tắt nếu máy chủ đệm buffer
        </label>

        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" wire:click="testTavily" class="btn btn-outline" wire:loading.attr="disabled" wire:target="testTavily">
                <span wire:loading.remove wire:target="testTavily">Kiểm tra Tavily</span>
                <span wire:loading wire:target="testTavily">Đang kiểm tra…</span>
            </button>
            @if ($tavilyConfigured)
                <button type="button" wire:click="clearTavily" class="btn btn-ghost text-signal">Xóa Tavily key</button>
            @endif
            <button type="button" wire:click="save" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Lưu cấu hình</span>
                <span wire:loading wire:target="save">Đang lưu…</span>
            </button>
        </div>
    </div>
</div>
