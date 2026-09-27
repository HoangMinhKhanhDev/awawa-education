<div class="space-y-6">
    <div class="page-head">
        <div class="min-w-0">
            <a href="{{ url()->previous() }}" wire:navigate class="text-sm text-ink-soft hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
                ← Quay lại
            </a>
            <h1 class="page-title mt-1">{{ $document->title }}</h1>
            <p class="page-sub flex flex-wrap items-center gap-x-3 gap-y-1">
                @if ($document->category)
                    <span class="chip chip-neutral">{{ $document->category }}</span>
                @endif
                <span class="tnum">{{ $document->sizeForHumans() }}</span>
                @if ($document->subject)
                    <span class="tnum" style="color: {{ $document->subject->color }}">{{ $document->subject->name }}</span>
                @endif
                @if ($document->creator)
                    <span class="tnum">— {{ $document->creator->name }}</span>
                @endif
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap gap-1.5">
            <a href="{{ $document->url() }}" download class="btn btn-outline px-3.5 py-2 text-xs">
                <x-icon name="file" class="h-4 w-4" />
                Tải về
            </a>
            @can('update', $document)
                <a href="{{ route('studio.documents') }}" wire:navigate class="btn btn-ghost px-3.5 py-2 text-xs">
                    Quản lý tài liệu
                </a>
            @endcan
        </div>
    </div>

    @if ($document->description)
        <p class="text-sm text-ink-soft dark:text-slate-400">{{ $document->description }}</p>
    @endif

    <div class="panel">
        @if ($html !== null)
            {{-- Dùng lại CSS markdown sẵn có của Notebook. --}}
            <div class="notebook-markdown px-5 py-5 text-[15px] leading-relaxed text-ink dark:text-slate-100">
                {!! $html !!}
            </div>
        @elseif ($tooLarge)
            <div class="alert alert-warning m-4">
                Tài liệu này quá lớn để hiển thị ngay trên trang. Hãy tải về để xem.
            </div>
        @elseif ($document->isViewable())
            <p class="empty">Không đọc được nội dung tài liệu. Hãy tải về để xem.</p>
        @else
            <div class="flex flex-col items-center gap-3 px-5 py-12 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-paper-2 text-ink-soft dark:bg-night-700 dark:text-slate-300">
                    <x-icon name="file" class="h-5 w-5" />
                </span>
                <p class="text-sm text-ink-soft dark:text-slate-400">
                    Định dạng <span class="font-medium">{{ $document->mime ?: 'không rõ' }}</span> không hiển thị được trong trình duyệt.
                </p>
                <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="btn btn-primary px-3.5 py-2 text-xs">
                    Mở tệp gốc
                </a>
            </div>
        @endif
    </div>
</div>
