<div class="flex h-full min-h-0 flex-col">
    <div class="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-rule px-4 dark:border-night-700">
        <div class="flex min-w-0 items-center gap-2">
            <p class="truncate text-sm font-semibold text-ink dark:text-white">{{ $notebook->title }}</p>
            <span class="hidden shrink-0 text-[11px] text-ink-faint sm:inline dark:text-slate-500">{{ $notebook->subject?->name }}</span>
        </div>
        @if ($messages->isNotEmpty())
            <button type="button" wire:click="clear" wire:confirm="Xóa toàn bộ hội thoại?"
                class="shrink-0 text-xs font-medium text-ink-faint hover:text-signal dark:text-slate-500">Xóa</button>
        @endif
    </div>

    <div id="notebook-chat-scroll" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5">
        @if ($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif

        @if ($truncated)
            <div class="alert alert-warning">Nguồn vượt giới hạn ngữ cảnh nên đã lược bớt một phần. Tắt bớt nguồn hoặc rút gọn tài liệu.</div>
        @endif

        @forelse ($messages as $message)
            @if ($message->role === 'user')
                <div class="flex justify-end" wire:key="msg-{{ $message->id }}">
                    <div class="max-w-[85%] rounded-[16px] rounded-br-[4px] bg-brand-600 px-4 py-2.5 text-sm leading-relaxed text-white">
                        {{ $message->content }}
                    </div>
                </div>
            @else
                @php $citations = collect($message->citationList())->keyBy('index'); @endphp
                <div class="flex justify-start" wire:key="msg-{{ $message->id }}">
                    <div class="max-w-[92%] rounded-[16px] rounded-bl-[4px] border border-rule bg-white px-4 py-3 text-sm leading-relaxed text-ink dark:border-night-700 dark:bg-night-800 dark:text-slate-200">
                        @foreach ($message->segments() as $segment)
                            @if ($segment['type'] === 'text')
                                <span class="whitespace-pre-line">{{ $segment['value'] }}</span>
                            @else
                                @php $cite = $citations->get($segment['index']); @endphp
                                <span class="relative inline-block align-baseline" x-data="{ open: false }">
                                    <button type="button"
                                        class="mx-0.5 inline-flex h-5 min-w-5 translate-y-[1px] items-center justify-center rounded-full bg-brand-100 px-1.5 text-[11px] font-semibold text-brand-700 align-middle dark:bg-brand-500/20 dark:text-brand-300"
                                        @mouseenter="open = true" @mouseleave="open = false" @click="open = ! open" @focus="open = true" @blur="open = false"
                                        title="Xem đoạn nguồn">
                                        {{ $segment['index'] }}
                                    </button>
                                    <span x-show="open" x-cloak x-transition.opacity
                                        class="panel absolute bottom-full left-0 z-40 mb-2 block w-80 max-w-[80vw] p-3 text-left shadow-lg">
                                        @if ($cite)
                                            <span class="mb-1 block text-xs font-semibold text-ink dark:text-white">{{ $cite['source_title'] }}</span>
                                            <span class="block max-h-48 overflow-y-auto whitespace-pre-line text-xs leading-relaxed text-ink-soft dark:text-slate-400">{{ \Illuminate\Support\Str::limit($cite['text'], 700) }}</span>
                                        @else
                                            <span class="block text-xs text-ink-faint dark:text-slate-500">Không tìm thấy đoạn nguồn.</span>
                                        @endif
                                    </span>
                                </span>
                            @endif
                        @endforeach

                        <p class="mt-2 text-[11px] text-ink-faint dark:text-slate-500">
                            {{ $message->model ?: 'AI' }}@if ($message->tokens) <span class="tnum">· {{ $message->tokens }} token</span>@endif
                        </p>
                    </div>
                </div>
            @endif
        @empty
            @unless ($streaming)
                <div class="mx-auto max-w-md pt-8 text-center">
                    <p class="text-sm text-ink-soft dark:text-slate-400">Bắt đầu hỏi hoặc yêu cầu nội dung từ nguồn.</p>
                    <div class="mt-5 grid gap-2 text-left">
                        @foreach ([
                            'Tóm tắt các ý chính trong nguồn',
                            'Liệt kê các dạng bài quan trọng trong nguồn',
                            'Soạn 5 câu trắc nghiệm về nội dung trong nguồn',
                        ] as $suggestion)
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
                <div class="max-w-[92%] rounded-[16px] rounded-bl-[4px] border border-rule bg-white px-4 py-3 text-sm leading-relaxed text-ink dark:border-night-700 dark:bg-night-800 dark:text-slate-200">
                    <span class="block whitespace-pre-line" wire:stream="answer"></span>
                    <span class="mt-1 inline-block animate-pulse text-xs text-ink-faint dark:text-slate-500">AI đang trả lời…</span>
                </div>
            </div>
        @endif
    </div>

    <div class="shrink-0 border-t border-rule p-3 dark:border-night-700">
        <form wire:submit="send" class="flex items-end gap-2 rounded-[16px] border border-rule bg-white px-3 py-2 dark:border-night-700 dark:bg-night-800">
            <textarea rows="1" class="max-h-32 flex-1 resize-none border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-faint focus:outline-none dark:text-slate-100"
                wire:model="prompt" placeholder="Hỏi hoặc yêu cầu nội dung từ nguồn…"
                wire:keydown.enter.prevent="send" @disabled($streaming)></textarea>

            <span class="tnum hidden shrink-0 text-[11px] text-ink-faint sm:inline dark:text-slate-500">{{ $sourceCount }} nguồn</span>

            <button type="submit" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="send" @disabled($streaming) aria-label="Gửi">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6" /></svg>
            </button>
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
    </script>
    @endscript
</div>
