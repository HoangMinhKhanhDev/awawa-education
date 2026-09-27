@props([
    'type',
    'model',
    'assignments' => [],
    'dueDays' => null,
    'note' => '',
])

@php
    $enum = \App\Enums\AssignableType::tryFrom($type);
    $active = $assignments[$type.'-'.$model->getKey()] ?? null;
@endphp

<div x-data="{ open: false }" class="inline-flex">
    @if ($active)
        <button type="button" class="btn btn-outline px-2.5 py-1.5 text-xs" title="{{ $active->progress()['completed'] }}/{{ $active->progress()['total'] }} học sinh đã xong"
            aria-label="Đang giao cho học sinh">
            <x-icon name="check" class="h-3.5 w-3.5 text-success dark:text-emerald-400" />
            Đang giao
        </button>
        <button type="button" wire:click="recallContent({{ $active->id }})"
            wire:confirm="Thu hồi khỏi học sinh? Bài đã nộp vẫn được giữ."
            class="ml-1.5 rounded-[10px] p-1.5 text-ink-faint hover:bg-paper-2 hover:text-signal dark:hover:bg-white/5 dark:hover:text-red-400"
            title="Thu hồi" aria-label="Thu hồi khỏi học sinh">
            <x-icon name="x" class="h-4 w-4" />
        </button>
    @else
        <button type="button" x-on:click="open = true" class="btn btn-outline px-2.5 py-1.5 text-xs">
            <x-icon name="upload" class="h-3.5 w-3.5" />
            Giao cho học sinh
        </button>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-end justify-center bg-night-900/60 p-0 sm:items-center sm:p-4"
            role="dialog" aria-modal="true" aria-label="Giao cho học sinh">
            <div class="w-full max-w-md rounded-t-[14px] bg-white p-6 sm:rounded-[14px] dark:bg-night-800">
                <h3 class="text-lg font-semibold text-ink dark:text-white">Giao cho học sinh</h3>
                <p class="mt-1 text-sm text-ink-soft dark:text-slate-400">
                    {{ $model->title }}
                    <span class="chip chip-neutral ml-1.5">{{ $enum?->label() }}</span>
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="label" for="assign-due-{{ $model->getKey() }}">Hạn (số ngày nữa)</label>
                        <input id="assign-due-{{ $model->getKey() }}" type="number" min="1" max="365"
                            class="input" wire:model="assignDueDays"
                            placeholder="Bỏ trống nếu không đặt hạn">
                    </div>
                    <div>
                        <label class="label" for="assign-note-{{ $model->getKey() }}">Ghi chú gửi kèm</label>
                        <input id="assign-note-{{ $model->getKey() }}" type="text" maxlength="1000"
                            class="input" wire:model="assignNote" placeholder="Ví dụ: Đọc trước buổi học thứ hai">
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn btn-ghost">Huỷ</button>
                    <button type="button" class="btn btn-primary" x-on:click="open = false; $wire.assignContent('{{ $type }}', {{ $model->getKey() }})">
                        Giao cho cả đội
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
