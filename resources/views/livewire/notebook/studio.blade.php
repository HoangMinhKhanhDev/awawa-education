<div class="flex h-full min-h-0 flex-col">
    <div class="flex h-12 shrink-0 items-center justify-between px-4">
        <h2 class="text-sm font-semibold text-ink dark:text-white">Studio</h2>
        <span class="tnum text-xs text-ink-faint dark:text-slate-500">{{ $artifacts->count() }}</span>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div class="space-y-2 px-4 pb-4">
            @if (session('notebook_status'))
                <div class="alert alert-success">{{ session('notebook_status') }}</div>
            @endif

            @if ($error)
                <div class="alert alert-error">{{ $error }}</div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                @forelse ($types as $type)
                    <button type="button" wire:click="openForm('{{ $type->value }}')"
                        class="flex items-center gap-2 rounded-[12px] border border-rule px-3 py-2.5 text-left transition-colors hover:bg-paper-2 dark:border-night-700 dark:hover:bg-white/5"
                        title="{{ $type->description() }}">
                        <x-icon :name="$type->icon()" class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
                        <span class="truncate text-xs font-medium text-ink dark:text-slate-200">{{ $type->label() }}</span>
                    </button>
                @empty
                    <p class="empty col-span-2">Môn này chưa bật tính năng tạo nội dung.</p>
                @endforelse
            </div>
        </div>

        <div class="border-t border-rule px-4 py-3 dark:border-night-700">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-ink dark:text-slate-200">Nội dung đã tạo</p>
                <select class="rounded-[10px] border border-rule bg-white px-2 py-1 text-[11px] text-ink-soft dark:border-night-700 dark:bg-night-800 dark:text-slate-300"
                    wire:model.live="filterType">
                    <option value="">Tất cả</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-2 divide-y divide-rule dark:divide-night-700">
                @forelse ($artifacts as $artifact)
                    <div class="group flex items-start gap-2 py-2.5" wire:key="artifact-{{ $artifact->id }}">
                        <button type="button" wire:click="openPreview({{ $artifact->id }})" class="min-w-0 flex-1 text-left">
                            <p class="line-clamp-2 text-sm text-ink dark:text-slate-100">{{ $artifact->title }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                <span class="chip chip-neutral">{{ \App\Enums\ArtifactType::from($artifact->type)->label() }}</span>
                                <span class="chip {{ $artifact->isPublished() ? 'chip-success' : 'chip-warning' }}">{{ $artifact->isPublished() ? 'Đã xuất bản' : 'Nháp' }}</span>
                            </p>
                        </button>
                        @unless ($artifact->isPublished())
                            <button type="button" wire:click="publish({{ $artifact->id }})" class="btn btn-outline shrink-0 px-2 py-1 text-[11px]">Xuất bản</button>
                        @endunless
                        <button type="button" wire:click="delete({{ $artifact->id }})" wire:confirm="Xóa nội dung này?"
                            class="shrink-0 rounded-[10px] p-1.5 text-ink-faint opacity-0 transition-opacity hover:bg-signal-soft hover:text-signal group-hover:opacity-100 dark:hover:bg-red-500/10" title="Xóa">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                @empty
                    <p class="empty">Chưa có nội dung nào.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal tạo nội dung --}}
    @if ($formType)
        @php $formArtifactType = \App\Enums\ArtifactType::from($formType); @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="w-full max-w-lg rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">{{ $formArtifactType->label() }}</h2>
                    <button type="button" wire:click="closeForm" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                @unless ($hasSources)
                    <div class="alert alert-warning mb-4">Chưa bật nguồn nào. Thêm và bật nguồn ở cột “Nguồn” để kết quả bám tài liệu.</div>
                @endunless

                <form wire:submit="generate" class="space-y-4">
                    <div>
                        <label class="label" for="st-instruction">Yêu cầu nội dung</label>
                        <textarea id="st-instruction" rows="3" class="input" wire:model="instruction"
                            placeholder="VD: Soạn nội dung trọng tâm chuyên đề bất đẳng thức cho đội tuyển."></textarea>
                        @error('instruction') <p class="mt-1.5 text-[13px] text-signal dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if ($formType === \App\Enums\ArtifactType::Questions->value)
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div>
                                <label class="label" for="st-count">Số câu</label>
                                <input id="st-count" type="number" min="1" max="20" class="input tnum" wire:model="count">
                            </div>
                            <div>
                                <label class="label" for="st-qt">Dạng</label>
                                <select id="st-qt" class="input" wire:model="questionType">
                                    <option value="mixed">Trộn lẫn</option>
                                    <option value="multiple_choice">Trắc nghiệm</option>
                                    <option value="fill_blank">Điền khuyết</option>
                                    <option value="essay">Tự luận</option>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="st-diff">Độ khó</label>
                                <select id="st-diff" class="input" wire:model="difficulty">
                                    <option value="easy">Dễ</option>
                                    <option value="medium">Trung bình</option>
                                    <option value="hard">Khó</option>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="st-points">Điểm/câu</label>
                                <input id="st-points" type="number" step="0.25" min="0.25" class="input tnum" wire:model="points">
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeForm" class="btn btn-ghost">Hủy</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="generate">
                            <span wire:loading.remove wire:target="generate">Tạo nội dung</span>
                            <span wire:loading wire:target="generate">AI đang tạo…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal xem trước --}}
    @if ($preview)
        @php $previewType = \App\Enums\ArtifactType::from($preview->type); @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold text-ink dark:text-white">{{ $preview->title }}</h2>
                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="chip chip-neutral">{{ $previewType->label() }}</span>
                            <span class="chip {{ $preview->isPublished() ? 'chip-success' : 'chip-warning' }}">{{ $preview->isPublished() ? 'Đã xuất bản' : 'Nháp' }}</span>
                        </p>
                    </div>
                    <button type="button" wire:click="closePreview" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto pr-1">
                    @if ($previewType === \App\Enums\ArtifactType::Questions)
                        @foreach ($preview->payload['items'] ?? [] as $index => $item)
                            <div class="rounded-[10px] border border-rule p-3 dark:border-night-700">
                                <p class="text-sm text-ink dark:text-slate-100">{{ $index + 1 }}. {{ $item['content'] }}</p>
                                @foreach ($item['options'] ?? [] as $option)
                                    <p class="mt-1 text-sm {{ ! empty($option['is_correct']) ? 'font-medium text-success' : 'text-ink-soft dark:text-slate-400' }}">— {{ $option['content'] }}</p>
                                @endforeach
                                @if (! empty($item['answer']))
                                    <p class="mt-1 text-xs text-ink-soft dark:text-slate-400">Đáp án: {{ $item['answer'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    @elseif ($previewType === \App\Enums\ArtifactType::Exam)
                        @foreach ($preview->payload['sections'] ?? [] as $section)
                            <div>
                                <p class="font-medium text-ink dark:text-slate-100">{{ $section['title'] }}</p>
                                @foreach ($section['questions'] ?? [] as $qIndex => $item)
                                    <p class="mt-1 text-sm text-ink-soft dark:text-slate-300">{{ $qIndex + 1 }}. {{ $item['content'] }}</p>
                                @endforeach
                            </div>
                        @endforeach
                    @elseif ($previewType === \App\Enums\ArtifactType::Flashcards)
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($preview->payload['cards'] ?? [] as $card)
                                <div class="rounded-[10px] border border-rule p-3 dark:border-night-700">
                                    <p class="text-sm font-medium text-ink dark:text-slate-100">{{ $card['front'] }}</p>
                                    <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">{{ $card['back'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($previewType === \App\Enums\ArtifactType::MindMap)
                        <ul class="space-y-1 text-sm text-ink-soft dark:text-slate-300">
                            @foreach ($preview->payload['nodes'] ?? [] as $node)
                                <li>{{ $node['label'] }}@if (! empty($node['parent'])) <span class="text-xs text-ink-faint">(thuộc {{ $node['parent'] }})</span>@endif</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="whitespace-pre-line text-sm leading-relaxed text-ink dark:text-slate-200">{{ $preview->text_content }}</div>
                    @endif
                </div>

                @unless ($preview->isPublished())
                    <div class="mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-rule pt-3 dark:border-night-700">
                        @if (in_array($previewType, [\App\Enums\ArtifactType::Document, \App\Enums\ArtifactType::StudyGuide, \App\Enums\ArtifactType::Briefing], true))
                            <label class="mr-auto flex cursor-pointer items-center gap-2 text-sm text-ink-soft dark:text-slate-300">
                                <input type="checkbox" wire:model="publishPublic" class="h-4 w-4 rounded border-rule-strong text-brand-600 focus:ring-brand-500 dark:border-night-700">
                                Công khai cho học sinh
                            </label>
                        @endif
                        <span class="mr-auto text-xs text-ink-faint dark:text-slate-500">Xuất bản vào: {{ $previewType->publishTarget() }}</span>
                        <button type="button" wire:click="publish({{ $preview->id }})" class="btn btn-primary" wire:loading.attr="disabled" wire:target="publish">
                            Xuất bản
                        </button>
                    </div>
                @endunless
            </div>
        </div>
    @endif
</div>
