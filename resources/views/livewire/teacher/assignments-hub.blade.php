<div class="space-y-6">
    <div class="page-head">
        <div>
            <h1 class="page-title">Giao bài</h1>
            <p class="page-sub">
                Giao và thu hồi đề thi, tài liệu, thông báo, sơ đồ kiến thức — theo dõi ai đã xem và ai đã làm xong.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($error)
        <div class="alert alert-error">{{ $error }}</div>
    @endif

    @if (! $subject)
        <div class="alert alert-warning">
            Bạn chưa được gán môn nào nên chưa giao được bài. Liên hệ quản trị viên.
        </div>
    @else
        {{-- Đang giao --}}
        <section class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Đang giao</h2>
                <span class="tnum text-sm text-ink-faint dark:text-slate-500">{{ $openAssignments->count() }} nội dung</span>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    wire:click="$set('typeFilter', null)"
                    class="chip {{ $typeFilter === null ? 'chip-brand' : 'chip-neutral' }}"
                >Tất cả</button>
                @foreach ($types as $type)
                    <button
                        type="button"
                        wire:click="$set('typeFilter', '{{ $type->value }}')"
                        class="chip {{ $typeFilter === $type->value ? 'chip-brand' : 'chip-neutral' }}"
                    >{{ $type->labelPlural() }}</button>
                @endforeach
            </div>

            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($openAssignments as $assignment)
                        @php
                            $type = $assignment->type();
                            $assignedModel = $assignment->assignable;
                            $stats = $assignment->progress();
                            $percent = $assignment->completionPercent();
                        @endphp
                        <div class="px-5 py-4" wire:key="open-{{ $assignment->id }}">
                            <div class="flex flex-wrap items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                    <x-icon :name="$type?->icon() ?? 'doc'" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-ink dark:text-slate-100">{{ $assignedModel?->title ?? '(đã xoá)' }}</p>
                                        @if ($type)
                                            <span class="chip chip-neutral">{{ $type->label() }}</span>
                                        @endif
                                        @if ($assignment->isOverdue())
                                            <span class="chip chip-signal">Trễ hạn</span>
                                        @endif
                                    </div>
                                    <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                        Giao {{ $assignment->assigned_at->format('d/m/Y H:i') }}
                                        @if ($assignment->due_at)<span class="mx-1.5">—</span>Hạn {{ $assignment->due_at->format('d/m/Y H:i') }}@endif
                                    </p>
                                    @if ($assignment->note)
                                        <p class="mt-1.5 text-sm text-ink-soft dark:text-slate-300">{{ $assignment->note }}</p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <button type="button" wire:click="showProgress({{ $assignment->id }})" class="btn btn-ghost px-2.5 py-1.5 text-xs">
                                        Tiến độ
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="recall({{ $assignment->id }})"
                                        wire:confirm="Thu hồi nội dung này? Học sinh sẽ không còn thấy, bài đã nộp vẫn được giữ."
                                        class="btn btn-outline px-2.5 py-1.5 text-xs"
                                    >Thu hồi</button>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                <div class="h-1.5 min-w-[140px] flex-1 overflow-hidden rounded-full bg-paper-2 dark:bg-night-700">
                                    <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $percent }}%"></div>
                                </div>
                                <p class="tnum shrink-0 text-xs text-ink-soft dark:text-slate-400">
                                    Xong {{ $stats['completed'] }}/{{ $stats['total'] }}
                                    <span class="mx-1.5 text-rule-strong dark:text-night-700">·</span>
                                    Đã mở {{ $stats['opened'] }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="empty">Chưa giao nội dung nào.</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Giao mới --}}
        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink dark:text-white">Giao nội dung mới</h2>

            <div class="panel panel-pad">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="assign-search">Tìm nội dung</label>
                        <input id="assign-search" type="search" class="input" wire:model.live.debounce.400ms="search" placeholder="Nhập tên đề, tài liệu…">
                    </div>
                    <div>
                        <label class="label" for="assign-due">Hạn (số ngày nữa)</label>
                        <input id="assign-due" type="number" min="1" max="365" class="input" wire:model="dueDays" placeholder="Bỏ trống nếu không đặt hạn">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="assign-note">Ghi chú gửi kèm</label>
                        <input id="assign-note" type="text" maxlength="1000" class="input" wire:model="note" placeholder="Ví dụ: Đọc trước buổi học thứ hai">
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($assignable as $option)
                        @php $model = $option['model']; @endphp
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="pick-{{ $option['type']->value }}-{{ $model->getKey() }}">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-paper-2 text-ink-soft dark:bg-night-700 dark:text-slate-300">
                                <x-icon :name="$option['type']->icon()" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink dark:text-slate-100">{{ $model->title }}</p>
                                <p class="tnum mt-0.5 truncate text-xs text-ink-faint dark:text-slate-500">
                                    <span class="chip chip-neutral mr-1.5">{{ $option['type']->label() }}</span>{{ $option['subtitle'] }}
                                </p>
                            </div>
                            <button
                                type="button"
                                   wire:click="assign('{{ $option['type']->value }}', {{ $model->getKey() }})"
                                   wire:loading.attr="disabled" wire:target="assign"
                                class="btn btn-primary shrink-0 px-3 py-1.5 text-xs"
                            >Giao</button>
                        </div>
                    @empty
                        <p class="empty">
                            Không còn nội dung nào để giao. Hãy bật thêm tính năng của môn ở trang Môn học, hoặc đăng tài liệu / thông báo trước.
                        </p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Đã thu hồi --}}
        @if ($recalledAssignments->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-ink dark:text-white">Đã thu hồi</h2>
                <div class="panel">
                    <div class="divide-y divide-rule dark:divide-night-700">
                        @foreach ($recalledAssignments as $assignment)
                            @php $type = $assignment->type(); @endphp
                            <div class="flex flex-wrap items-center gap-3 px-5 py-3.5" wire:key="recalled-{{ $assignment->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink-soft dark:text-slate-300">
                                        {{ $assignment->assignable?->title ?? '(đã xoá)' }}
                                    </p>
                                    <p class="tnum mt-0.5 text-xs text-ink-faint dark:text-slate-500">
                                        Thu hồi {{ $assignment->recalled_at->format('d/m/Y H:i') }}
                                        @if ($assignment->recall_reason)<span class="mx-1.5">—</span>{{ $assignment->recall_reason }}@endif
                                    </p>
                                </div>
                                <button type="button" wire:click="reassign({{ $assignment->id }})" class="btn btn-outline shrink-0 px-3 py-1.5 text-xs">
                                    Giao lại
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endif

    {{-- Tiến độ từng học sinh --}}
    @if ($progress)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/60 p-0 sm:items-center sm:p-4"
            role="dialog" aria-modal="true" aria-label="Tiến độ giao bài">
            <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-ink dark:text-white">
                            {{ $progress->assignable?->title ?? '(đã xoá)' }}
                        </h2>
                        <p class="tnum text-xs text-ink-faint dark:text-slate-500">
                            Giao {{ $progress->assigned_at->format('d/m/Y H:i') }} · xong {{ $progress->progress()['completed'] }}/{{ $progress->progress()['total'] }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeProgress" class="rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 dark:hover:bg-white/5" aria-label="Đóng">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                @php $receipts = $progressReceipts @endphp

                <div class="divide-y divide-rule dark:divide-night-700">
                    @forelse ($receipts as $receipt)
                        <div class="flex items-center gap-3 py-2.5" wire:key="receipt-{{ $receipt->id }}">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-paper-2 text-[11px] font-semibold text-ink-soft dark:bg-night-700 dark:text-slate-300">
                                {{ $receipt->user?->initials() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-ink dark:text-slate-100">{{ $receipt->user?->name }}</p>
                                <p class="tnum text-[11px] text-ink-faint dark:text-slate-500">
                                    @if ($receipt->completed_at)Xong {{ $receipt->completed_at->format('d/m H:i') }}
                                    @elseif ($receipt->opened_at)Đã mở {{ $receipt->opened_at->format('d/m H:i') }}
                                    @elseif ($receipt->delivered_at)Chưa mở
                                    @else Chưa nhận @endif
                                </p>
                            </div>
                            <span class="chip shrink-0
                                {{ $receipt->isCompleted() ? 'chip-success' : ($receipt->isOpened() ? 'chip-brand' : 'chip-neutral') }}">
                                {{ $receipt->isCompleted() ? 'Đã xong' : ($receipt->isOpened() ? 'Đang xem' : 'Chưa mở') }}
                            </span>
                        </div>
                    @empty
                        <p class="empty">Đội tuyển chưa có học sinh nào nên chưa có ai nhận.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
