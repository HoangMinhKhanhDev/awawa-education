<div class="flex h-full min-h-0 flex-col">
    <div class="grid min-h-14 shrink-0 grid-cols-[minmax(0,1fr)_minmax(12rem,2fr)_minmax(0,1fr)] items-center gap-2 border-b border-rule px-4 dark:border-night-700">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink dark:text-white">{{ $notebook->title }}</p>
            <p class="hidden truncate text-[11px] text-ink-faint sm:block dark:text-slate-500">{{ $notebook->subject?->name }}</p>
        </div>

        @if ($providers !== [])
            <div class="mx-auto flex min-w-0 max-w-full items-center gap-1.5 rounded-full border border-rule bg-white p-1 dark:border-night-700 dark:bg-night-800">
                <label class="sr-only" for="notebook-provider">Nhà cung cấp AI</label>
                <select id="notebook-provider" wire:model="selectedProviderKey" wire:change="providerChanged"
                    class="max-w-28 border-0 bg-transparent py-1 pl-2 pr-6 text-xs font-medium text-ink focus:ring-0 sm:max-w-40 dark:text-slate-200">
                    @foreach ($providers as $provider)
                        <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                    @endforeach
                </select>
                <span class="h-5 w-px bg-rule dark:bg-night-700"></span>
                <label class="sr-only" for="notebook-model">Model AI</label>
                <select id="notebook-model" wire:model="selectedModel" wire:change="saveSelectedModel"
                    class="min-w-0 max-w-36 border-0 bg-transparent py-1 pl-1 pr-6 text-xs font-semibold text-brand-700 focus:ring-0 sm:max-w-64 dark:text-brand-300">
                    @foreach ($availableModels as $model)
                        <option value="{{ $model['id'] }}">{{ $model['name'] }}{{ $model['free'] ? ' · miễn phí' : '' }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="refreshModels" wire:loading.attr="disabled" wire:target="refreshModels,providerChanged"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-ink-faint hover:bg-paper-2 hover:text-ink disabled:opacity-50 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                    title="Tải lại danh sách model" aria-label="Tải lại danh sách model">
                    <x-icon name="refresh" class="h-3.5 w-3.5" />
                </button>
            </div>
        @else
            <p class="text-center text-xs text-ink-faint dark:text-slate-500">Chưa có model AI khả dụng</p>
        @endif

        <div class="flex justify-end">
            @if ($messages->isNotEmpty())
                <button type="button" wire:click="clear" wire:confirm="Xóa toàn bộ hội thoại?"
                    class="shrink-0 text-xs font-medium text-ink-faint hover:text-signal dark:text-slate-500">Xóa chat</button>
            @endif
        </div>
    </div>
    @if ($modelError)
        <p class="border-b border-rule px-4 py-1 text-center text-[11px] text-signal dark:border-night-700 dark:text-red-400">{{ $modelError }}</p>
    @endif

    <div id="notebook-chat-scroll" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5">
        @if ($error)
            <div class="alert alert-error flex items-center justify-between gap-3">
                <span>{{ $error }}</span>
                <button type="button" wire:click="retry" wire:loading.attr="disabled" wire:target="retry,streamAnswer"
                    @disabled($streaming) class="btn btn-outline shrink-0 px-2 py-1 text-xs">Thử lại</button>
            </div>
        @endif

        @if ($truncated)
            <div class="alert alert-warning">Đang gửi các đoạn liên quan nhất; một phần nguồn được lược bớt để giữ ngữ cảnh gọn. Thử tắt nguồn không cần hoặc hỏi cụ thể hơn.</div>
        @endif

        @forelse ($messages as $message)
            @if ($message->role === 'user')
                <div class="flex justify-end" wire:key="msg-{{ $message->id }}">
                    <div class="max-w-[85%] rounded-[16px] rounded-br-[4px] bg-brand-600 px-4 py-2.5 text-sm leading-relaxed text-white">
                        {{ $message->content }}
                    </div>
                </div>
            @else
                <div class="flex justify-start" wire:key="msg-{{ $message->id }}">
                    <div class="max-w-[46rem] rounded-[16px] rounded-bl-[4px] border border-rule bg-white px-4 py-3 text-sm leading-relaxed text-ink dark:border-night-700 dark:bg-night-800 dark:text-slate-200">
                        <div class="notebook-answer">{!! $answers[$message->id] ?? '' !!}</div>

                        <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-ink-faint dark:text-slate-500">
                            <span>{{ $message->model ?: 'AI' }}@if ($message->tokens) <span class="tnum">· {{ $message->tokens }} token</span>@endif</span>
                            @if ($message->id === $messages->last()?->id)
                                <button type="button" wire:click="regenerate({{ $message->id }})" wire:loading.attr="disabled" wire:target="regenerate"
                                    class="font-medium hover:text-brand-700 dark:hover:text-brand-300">Tạo lại</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @empty
            @unless ($streaming)
                <div class="mx-auto max-w-md pt-8 text-center">
                    <p class="font-serif text-xl font-semibold text-ink dark:text-white">Bạn muốn tìm hiểu điều gì?</p>
                    <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">Câu trả lời sẽ dựa trên {{ $sourceCount }} nguồn đang bật.</p>
                    <div class="mt-5 grid gap-2 text-left">
                        @foreach (['Tóm tắt các ý chính trong nguồn', 'Giải thích khái niệm khó bằng ví dụ', 'Soạn câu hỏi ôn tập từ nguồn'] as $suggestion)
                            <button type="button" wire:click="$set('prompt', @js($suggestion))"
                                class="rounded-[12px] border border-rule px-4 py-3 text-sm text-ink-soft transition-colors hover:bg-paper-2 dark:border-night-700 dark:text-slate-300 dark:hover:bg-white/5">
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endunless
        @endforelse

        @if ($streaming)
            <div class="flex justify-start">
                <div class="max-w-[46rem] rounded-[16px] rounded-bl-[4px] border border-rule bg-white px-4 py-3 text-sm leading-relaxed text-ink dark:border-night-700 dark:bg-night-800 dark:text-slate-200">
                    <span id="notebook-chat-stream" class="notebook-answer-stream block whitespace-pre-line" wire:stream="answer"></span>
                    <span class="mt-1 inline-block animate-pulse text-xs text-ink-faint dark:text-slate-500">Đang đọc nguồn và trả lời…</span>
                </div>
            </div>
        @endif
    </div>

    <div class="shrink-0 border-t border-rule p-3 dark:border-night-700">
        <form wire:submit="send" class="rounded-[18px] border border-rule bg-white p-2.5 shadow-sm dark:border-night-700 dark:bg-night-800">
            <textarea rows="2" class="max-h-40 w-full resize-y border-0 bg-transparent px-1 py-1 text-sm text-ink placeholder:text-ink-faint focus:outline-none dark:text-slate-100"
                wire:model="prompt" placeholder="Hỏi về tài liệu, yêu cầu giải thích hoặc tạo nội dung…"
                wire:keydown.enter.prevent="send" @disabled($streaming)></textarea>
            <div class="flex items-center justify-between gap-2 border-t border-rule pt-2 dark:border-night-700">
                <div class="relative flex min-w-0 items-center gap-2" x-data="{ sourcesOpen: false }">
                    <button type="button" @click="sourcesOpen = ! sourcesOpen"
                        class="flex min-w-0 items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                        aria-label="Chọn nguồn cho câu hỏi">
                        <x-icon name="book" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ count($selectedSourceIds) }} / {{ $sourceCount }} nguồn</span>
                        <x-icon name="chevron-down" class="h-3 w-3 shrink-0" />
                    </button>
                    <div x-show="sourcesOpen" x-cloak @click.outside="sourcesOpen = false"
                        class="panel absolute bottom-10 left-0 z-40 max-h-72 w-80 max-w-[90vw] overflow-y-auto p-2 shadow-lg">
                        <p class="px-2 py-1.5 text-xs font-semibold text-ink dark:text-white">Nguồn cho câu hỏi này</p>
                        @forelse ($sources as $source)
                            <label class="flex cursor-pointer items-start gap-2 rounded-[8px] px-2 py-2 hover:bg-paper-2 dark:hover:bg-white/5" wire:key="chat-source-{{ $source->id }}">
                                <input type="checkbox" value="{{ $source->id }}" wire:model.live="selectedSourceIds"
                                    class="mt-0.5 h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                <span class="min-w-0 text-xs text-ink dark:text-slate-200">{{ $source->title }}</span>
                            </label>
                        @empty
                            <p class="px-2 py-2 text-xs text-ink-faint dark:text-slate-500">Thêm và bật nguồn ở cột bên trái.</p>
                        @endforelse
                    </div>
                </div>

                <button type="submit" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                    wire:loading.attr="disabled" wire:target="send" @disabled($streaming || blank($prompt)) aria-label="Gửi">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" /></svg>
                </button>
            </div>
        </form>
        @error('prompt') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    @script
    <script>
        window.__awawaScrollChat = () => {
            const el = document.getElementById('notebook-chat-scroll');
            if (el) el.scrollTop = el.scrollHeight;
        };
        window.__awawaScrollChat();

        // Trong lúc chờ chữ tới, chuẩn hoá gạch đầu dòng Markdown để không thấy dấu * thô.
        const bindStreamNormalizer = () => {
            const el = document.getElementById('notebook-chat-stream');
            if (!el || el.dataset.mdNormalizer === '1') return;
            el.dataset.mdNormalizer = '1';
            const apply = () => {
                const text = el.textContent || '';
                const next = text.replace(/^[ \t]*[*+-][ \t]+/gm, '• ');
                if (next !== text) el.textContent = next;
            };
            new MutationObserver(apply).observe(el, { childList: true, characterData: true, subtree: true });
        };
        bindStreamNormalizer();
        document.addEventListener('livewire:initialized', () => Livewire.hook('morphed', bindStreamNormalizer));
    </script>
    @endscript
</div>
