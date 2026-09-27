<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Trang đọc tài liệu văn bản thuần.
 *
 * Tài liệu AI lưu dạng Markdown trên ổ đĩa. Nếu đưa thẳng link file cho trình
 * duyệt thì nó hiện mã nguồn thô, và trên Apache hay thiếu charset nên tiếng Việt
 * bị lỗi. Ở đây render thành HTML với `charset=utf-8` sẵn trong trang.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public int $documentId;

    public function mount(Document $document): void
    {
        Gate::authorize('read', $document);

        $this->documentId = $document->id;
    }

    public function render(): View
    {
        $document = Document::query()->with(['subject', 'creator'])->findOrFail($this->documentId);

        $raw = $document->isViewable() ? $document->readContent() : null;

        return view('livewire.documents.show', [
            'document' => $document,
            // `html_input: strip` để nội dung do AI sinh không mang theo thẻ HTML.
            'html' => $raw === null ? null : Str::markdown($raw, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'tooLarge' => $document->isViewable() && (int) $document->size > Document::MAX_VIEWABLE_BYTES,
        ]);
    }
}
