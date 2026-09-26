<?php

namespace App\Livewire\Notebook;

use App\Models\Document;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookChunk;
use App\Models\NotebookSource;
use App\Models\Question;
use App\Services\Notebook\HighlightPicker;
use App\Services\Notebook\SourceIngestor;
use App\Services\Notebook\WebSourceFinder;
use App\Support\NotebookConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Sources extends Component
{
    use WithFileUploads;

    public const MAX_FILES_PER_BATCH = 10;

    #[Locked]
    public int $notebookId;

    /** Loại nguồn đang thêm; rỗng nghĩa là đang ở màn lưới chọn. */
    public string $addType = '';

    public bool $addOpen = false;

    public string $internalType = 'document';

    public string $title = '';

    public string $text = '';

    public string $urlText = '';

    /** @var array<int, UploadedFile> */
    public $files = [];

    public ?int $documentId = null;

    public ?int $questionId = null;

    public ?int $examId = null;

    public ?int $viewingSourceId = null;

    public ?int $highlightChunkId = null;

    public string $viewerTab = 'highlights';

    public ?int $editingSourceId = null;

    public string $sourceTitleDraft = '';

    public string $sourceQuery = '';

    public string $sourceTypeFilter = '';

    /** @var array<int, string> */
    public array $urlFailed = [];

    /** @var array<int, string> */
    public array $urlSkipped = [];

    public ?string $error = null;

    public string $webTopic = '';

    /** @var array<int, array<string, mixed>> */
    public array $webResults = [];

    /** @var array<int, int|string> */
    public array $webSelected = [];

    public bool $webSearching = false;

    public function mount(int $notebookId): void
    {
        $this->notebookId = $notebookId;
        $this->guard();
    }

    protected function notebook(): Notebook
    {
        return Notebook::query()->findOrFail($this->notebookId);
    }

    protected function guard(): void
    {
        abort_unless($this->notebook()->isOwnedBy(auth()->user()), 403);
    }

    public function openAddForm(string $type): void
    {
        $this->guard();

        abort_unless(in_array($type, ['', 'text', 'file', 'url', 'internal'], true), 404);

        $this->addOpen = true;
        $this->addType = $type;
        $this->error = null;
        $this->urlFailed = [];
        $this->urlSkipped = [];
        $this->resetErrorBag();
    }

    public function closeAddForm(): void
    {
        $this->addOpen = false;
        $this->addType = '';
        $this->error = null;
        $this->urlFailed = [];
        $this->urlSkipped = [];
    }

    public function updatedFiles(): void
    {
        $this->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES_PER_BATCH],
        ], [
            'files.max' => 'Mỗi lượt chỉ thêm tối đa '.self::MAX_FILES_PER_BATCH.' tệp.',
        ]);
    }

    public function addFiles(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES_PER_BATCH],
            'files.*' => ['file', 'max:'.NotebookConfig::maxFileKilobytes(), 'mimes:pdf,docx,txt,md,csv'],
            'title' => ['nullable', 'string', 'max:180'],
        ], [
            'files.required' => 'Chọn ít nhất một tệp để tải lên.',
            'files.max' => 'Mỗi lượt chỉ thêm tối đa '.self::MAX_FILES_PER_BATCH.' tệp.',
            'files.*.max' => 'Tệp tối đa '.NotebookConfig::maxFileMegabytes().'MB.',
            'files.*.mimes' => 'Chỉ hỗ trợ PDF, DOCX, TXT, MD, CSV.',
        ]);

        $added = 0;
        $failed = [];

        foreach ($this->files as $file) {
            if (! $this->canAddSource()) {
                break;
            }

            $source = $ingestor->fromUpload($this->notebook(), $file, $this->title ?: null);

            if ($source->status === 'failed') {
                $failed[] = $file->getClientOriginalName().' — '.($source->error ?: 'không đọc được nội dung');

                continue;
            }

            $this->dispatch('notebook-source-added', sourceId: $source->id);
            $added++;
        }

        $this->files = [];
        $this->title = '';

        if ($added > 0) {
            session()->flash('notebook_status', "Đã thêm {$added} tệp thành nguồn.");
        }

        if ($failed !== []) {
            $this->error = 'Không đọc được: '.implode('; ', array_slice($failed, 0, 3));
        }
    }

    public function addText(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'text' => ['required', 'string', 'min:10'],
        ], [
            'title.required' => 'Nhập tiêu đề nguồn.',
            'text.required' => 'Dán nội dung văn bản.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $this->afterAdd($ingestor->fromText($this->notebook(), $this->title, $this->text));
        $this->title = '';
        $this->text = '';
        $this->closeAddForm();
    }

    public function addUrls(SourceIngestor $ingestor, WebSourceFinder $finder): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;
        $this->urlFailed = [];
        $this->urlSkipped = [];

        $candidates = $this->parseUrlInput($this->urlText);

        if ($candidates === []) {
            $this->error = 'Dán ít nhất một đường dẫn, mỗi dòng một link.';

            return;
        }

        $existingKeys = $this->notebook()->sources()
            ->whereNotNull('url')
            ->pluck('url')
            ->map(fn (string $url): string => $this->urlKey($url))
            ->all();

        $unique = [];

        foreach ($candidates as $url) {
            $key = $this->urlKey($url);

            if (in_array($key, $existingKeys, true) || isset($unique[$key])) {
                $this->urlSkipped[] = $url;

                continue;
            }

            $unique[$key] = $url;
        }

        $remaining = max(0, NotebookConfig::maxSources() - $this->notebook()->sources()->count());
        $urls = array_slice(array_values($unique), 0, $remaining);

        if ($urls === []) {
            $this->error = 'Những đường dẫn này đã có trong notebook, hoặc đã đạt giới hạn nguồn.';

            return;
        }

        try {
            $contents = $finder->extract($urls);
        } catch (\Throwable) {
            $contents = [];
        }

        $added = 0;

        foreach ($urls as $url) {
            $content = $contents[$url] ?? null;

            if (! is_string($content) || blank($content)) {
                $this->urlFailed[] = $url;

                continue;
            }

            $source = $ingestor->fromText($this->notebook(), $this->titleFromUrl($url), $content, 'web', ['url' => $url]);
            $this->dispatch('notebook-source-added', sourceId: $source->id);
            $added++;
        }

        $this->urlText = '';

        session()->flash('notebook_status', $added > 0
            ? "Đã thêm {$added} nguồn từ đường dẫn."
            : 'Không lấy được nội dung từ các đường dẫn đã dán.');
    }

    /**
     * @return array<int, string>
     */
    protected function parseUrlInput(string $input): array
    {
        $parts = preg_split('/\s+/u', trim($input)) ?: [];
        $urls = [];

        foreach ($parts as $part) {
            $url = rtrim($part, '.,;');

            if (mb_strlen($url) > 1000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            if (! in_array(mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    protected function urlKey(string $url): string
    {
        return rtrim(mb_strtolower(trim($url)), '/');
    }

    protected function titleFromUrl(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: $url);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = $path === '' ? '' : basename($path);
        $label = $segment === '' ? '' : trim(mb_strtolower(str_replace(['-', '_', '%20'], ' ', rawurldecode($segment))));

        return Str::limit(trim($host.($label === '' ? '' : ' · '.$label)), 180, '');
    }

    public function addInternalSource(SourceIngestor $ingestor): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        match ($this->internalType) {
            'question' => $this->addQuestion($ingestor),
            'exam' => $this->addExam($ingestor),
            default => $this->addDocument($ingestor),
        };
    }

    public function addDocument(SourceIngestor $ingestor): void
    {
        $this->validate([
            'documentId' => ['required', 'integer', Rule::exists('documents', 'id')],
        ], [
            'documentId.required' => 'Chọn một tài liệu trong môn.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $document = Document::query()
            ->where('subject_id', $this->notebook()->subject_id)
            ->findOrFail($this->documentId);

        $this->afterAdd($ingestor->fromDocument($this->notebook(), $document));
        $this->documentId = null;
        $this->closeAddForm();
    }

    public function addQuestion(SourceIngestor $ingestor): void
    {
        $this->validate([
            'questionId' => ['required', 'integer', Rule::exists('questions', 'id')],
        ], [
            'questionId.required' => 'Chọn một câu hỏi trong môn.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $question = Question::query()
            ->where('subject_id', $this->notebook()->subject_id)
            ->where('is_active', true)
            ->findOrFail($this->questionId);

        if (! $this->sourceReferenceIsNew('question', $question->getMorphClass(), $question->id)) {
            $this->error = 'Câu hỏi này đã được thêm làm nguồn.';

            return;
        }

        $this->afterAdd($ingestor->fromQuestion($this->notebook(), $question));
        $this->questionId = null;
        $this->closeAddForm();
    }

    public function addExam(SourceIngestor $ingestor): void
    {
        $this->validate([
            'examId' => ['required', 'integer', Rule::exists('exams', 'id')],
        ], [
            'examId.required' => 'Chọn một đề thi trong môn.',
        ]);

        if (! $this->canAddSource()) {
            return;
        }

        $exam = Exam::query()
            ->where('subject_id', $this->notebook()->subject_id)
            ->findOrFail($this->examId);

        if (! $this->sourceReferenceIsNew('exam', $exam->getMorphClass(), $exam->id)) {
            $this->error = 'Đề thi này đã được thêm làm nguồn.';

            return;
        }

        $this->afterAdd($ingestor->fromExam($this->notebook(), $exam));
        $this->examId = null;
        $this->closeAddForm();
    }

    protected function sourceReferenceIsNew(string $type, string $referenceType, int $referenceId): bool
    {
        return ! $this->notebook()->sources()
            ->where('type', $type)
            ->where('ref_type', $referenceType)
            ->where('ref_id', $referenceId)
            ->exists();
    }

    protected function canAddSource(): bool
    {
        if ($this->notebook()->sources()->count() >= NotebookConfig::maxSources()) {
            $this->error = 'Notebook đã đạt giới hạn '.NotebookConfig::maxSources().' nguồn. Hãy xoá nguồn cũ trước.';

            return false;
        }

        return true;
    }

    protected function afterAdd(NotebookSource $source): void
    {
        if ($source->status === 'failed') {
            $this->error = $source->error ?: 'Không trích được nội dung nguồn.';

            return;
        }

        $this->dispatch('notebook-source-added', sourceId: $source->id);
        session()->flash('notebook_status', 'Đã thêm nguồn: '.$source->title);
    }

    public function toggle(int $sourceId): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);
        $source->forceFill(['is_enabled' => ! $source->is_enabled])->save();
        $this->dispatch('notebook-sources-changed');
    }

    public function selectAllVisible(bool $enabled = true): void
    {
        $this->guard();

        $ids = $this->visibleSources()->pluck('id');

        $this->notebook()->sources()->whereKey($ids)->update(['is_enabled' => $enabled]);
        $this->dispatch('notebook-sources-changed');
    }

    public function remove(int $sourceId, SourceIngestor $ingestor): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);

        if ($this->viewingSourceId === $source->id) {
            $this->viewingSourceId = null;
            $this->highlightChunkId = null;
        }

        $ingestor->remove($source);
        $this->dispatch('notebook-sources-changed');
    }

    public function view(int $sourceId): void
    {
        $this->guard();
        $this->viewingSourceId = $sourceId;
        $this->highlightChunkId = null;
        $this->viewerTab = 'highlights';
        $this->dispatch('notebook-open-viewer');
    }

    #[On('notebook-open-cited-source')]
    public function viewCitation(int $sourceId, int $chunkId): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);
        abort_unless($source->chunks()->whereKey($chunkId)->exists(), 404);

        $this->viewingSourceId = $source->id;
        $this->highlightChunkId = $chunkId;
        $this->viewerTab = 'full';
        $this->dispatch('notebook-open-viewer');
    }

    public function askAbout(int $sourceId): void
    {
        $this->guard();

        $source = $this->notebook()->sources()->findOrFail($sourceId);
        abort_unless($source->status === 'ready', 404);

        $this->dispatch('notebook-ask-source', sourceId: $source->id, sourceTitle: $source->title);
    }

    public function retrySource(int $sourceId, SourceIngestor $ingestor, WebSourceFinder $finder): void
    {
        $this->guard();
        $this->error = null;
        $source = $this->notebook()->sources()->findOrFail($sourceId);

        if ($source->status !== 'failed') {
            return;
        }

        try {
            if ($source->type === 'web' && filled($source->url)) {
                $extracted = $finder->extract([(string) $source->url]);
                $content = $extracted[$source->url] ?? null;

                if (blank($content) && $extracted !== []) {
                    $content = reset($extracted);
                }

                $source = $ingestor->retry($source, (string) ($content ?? ''));
            } else {
                $source = $ingestor->retry($source);
            }
        } catch (\Throwable $exception) {
            $source->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)])->save();
        }

        if ($source->fresh()->status === 'ready') {
            $this->dispatch('notebook-source-added', sourceId: $source->id);
            session()->flash('notebook_status', 'Đã xử lý lại nguồn: '.$source->title);
        } else {
            $this->error = $source->fresh()->error ?: 'Không thể xử lý lại nguồn.';
        }
    }

    public function startRenaming(int $sourceId): void
    {
        $this->guard();
        $source = $this->notebook()->sources()->findOrFail($sourceId);
        $this->editingSourceId = $source->id;
        $this->sourceTitleDraft = $source->title;
    }

    public function renameSource(): void
    {
        $this->guard();

        if ($this->editingSourceId === null) {
            return;
        }

        $validated = $this->validate([
            'sourceTitleDraft' => ['required', 'string', 'min:2', 'max:180'],
        ], [
            'sourceTitleDraft.required' => 'Nhập tên nguồn.',
        ]);

        $this->notebook()->sources()->findOrFail($this->editingSourceId)->update(['title' => $validated['sourceTitleDraft']]);
        $this->editingSourceId = null;
        $this->sourceTitleDraft = '';
    }

    public function moveSource(int $sourceId, string $direction): void
    {
        $this->guard();
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $sources = $this->notebook()->sources()->orderBy('order')->orderBy('id')->get();
        $index = $sources->search(fn (NotebookSource $source): bool => $source->id === $sourceId);

        if ($index === false) {
            abort(404);
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($sources[$targetIndex])) {
            return;
        }

        $current = $sources[$index];
        $target = $sources[$targetIndex];
        $currentOrder = $current->order;
        $current->forceFill(['order' => $target->order])->save();
        $target->forceFill(['order' => $currentOrder])->save();
    }

    public function searchWeb(WebSourceFinder $finder): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;
        $this->webResults = [];
        $this->webSelected = [];

        $this->validate([
            'webTopic' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'webTopic.required' => 'Nhập chủ đề để tìm nguồn web.',
        ]);

        if (! $finder->configured()) {
            $this->error = 'Chưa cấu hình Tavily API key. Báo quản trị viên thêm trong mục API key.';

            return;
        }

        $this->webSearching = true;

        try {
            $results = $finder->find($this->webTopic, $this->notebook()->subject_id, auth()->id());
        } catch (\Throwable $exception) {
            $this->error = $exception->getMessage();
            $this->webSearching = false;

            return;
        }

        $this->webSearching = false;
        $this->webResults = $results;

        foreach ($results as $index => $result) {
            if (! empty($result['keep'])) {
                $this->webSelected[] = $index;
            }
        }

        if ($results === []) {
            $this->error = 'Không tìm thấy nguồn phù hợp. Thử chủ đề khác.';

            return;
        }

        $this->addOpen = false;
        $this->addType = '';
    }

    public function addWebSources(SourceIngestor $ingestor, WebSourceFinder $finder): void
    {
        $this->guard();
        $this->error = null;

        if ($this->webSelected === []) {
            $this->error = 'Chọn ít nhất một nguồn để thêm.';

            return;
        }

        $selectedResults = [];

        foreach ($this->webSelected as $index) {
            if (! is_int($index) && (! is_string($index) || ! ctype_digit($index))) {
                continue;
            }

            $result = $this->webResults[(int) $index] ?? null;
            $url = is_array($result) ? ($result['url'] ?? null) : null;

            if (is_array($result)
                && is_string($url)
                && mb_strlen($url) <= 1000
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $selectedResults[] = $result;
            }
        }

        $urls = array_values(array_map(fn (array $result): string => (string) $result['url'], $selectedResults));
        $extractionFailed = false;

        try {
            $extractedContent = $finder->extract($urls);
        } catch (\Throwable) {
            $extractedContent = [];
            $extractionFailed = true;
        }

        $added = 0;

        foreach ($selectedResults as $result) {
            if (! $this->canAddSource()) {
                break;
            }

            $url = (string) $result['url'];
            $content = $extractedContent[$url] ?? null;

            if (blank($content)) {
                $content = is_string($result['content'] ?? null) ? $result['content'] : null;
                $extractionFailed = true;
            }

            if (! is_string($content) || blank($content)) {
                continue;
            }

            $title = is_string($result['title'] ?? null) && filled($result['title'])
                ? Str::limit(trim($result['title']), 180, '')
                : $this->titleFromUrl($url);

            $source = $ingestor->fromText($this->notebook(), $title, $content, 'web', ['url' => $url]);
            $this->dispatch('notebook-source-added', sourceId: $source->id);

            $added += $source->status === 'ready' ? 1 : 0;
        }

        $this->webResults = [];
        $this->webSelected = [];
        $this->webTopic = '';

        if ($added > 0) {
            $message = "Đã thêm {$added} nguồn web.";

            if ($extractionFailed) {
                $message .= ' Một số trang chỉ lấy được đoạn trích tìm kiếm.';
            } else {
                $message .= ' Đã lấy nội dung đầy đủ của trang.';
            }

            session()->flash('notebook_status', $message);
        } elseif ($this->error === null) {
            $this->error = 'Không lấy được nội dung từ các trang đã chọn.';
        }
    }

    public function closeViewer(): void
    {
        $this->viewingSourceId = null;
        $this->highlightChunkId = null;
    }

    /**
     * Mở tab toàn văn và tô sáng đoạn chứa câu trích đang xem.
     */
    public function jumpToChunk(int $chunkId): void
    {
        $this->guard();

        if ($this->viewingSourceId === null) {
            return;
        }

        abort_unless(
            $this->notebook()->sources()->whereKey($this->viewingSourceId)->whereHas('chunks', fn ($query) => $query->whereKey($chunkId))->exists(),
            404,
        );

        $this->viewerTab = 'full';
        $this->highlightChunkId = $chunkId;
    }

    /**
     * @return Collection<int, NotebookSource>
     */
    protected function visibleSources(): Collection
    {
        $notebook = $this->notebook();

        return $notebook->sources()
            ->withCount('chunks')
            ->when($this->sourceQuery !== '', fn ($query) => $query->where('title', 'like', '%'.$this->sourceQuery.'%'))
            ->when($this->sourceTypeFilter !== '', fn ($query) => $query->where('type', $this->sourceTypeFilter))
            ->get();
    }

    public function render(): View
    {
        $notebook = $this->notebook();
        $viewing = $this->viewingSourceId
            ? $notebook->sources()->with('chunks')->find($this->viewingSourceId)
            : null;

        return view('livewire.notebook.sources', [
            'sources' => $this->visibleSources(),
            'allSourcesCount' => $notebook->sources()->count(),
            'documents' => $this->addType === 'internal' && $this->internalType === 'document'
                ? Document::query()->where('subject_id', $notebook->subject_id)->orderByDesc('created_at')->limit(50)->get()
                : collect(),
            'questions' => $this->addType === 'internal' && $this->internalType === 'question'
                ? Question::query()->where('subject_id', $notebook->subject_id)->where('is_active', true)->latest()->limit(100)->get()
                : collect(),
            'exams' => $this->addType === 'internal' && $this->internalType === 'exam'
                ? Exam::query()->where('subject_id', $notebook->subject_id)->latest()->limit(50)->get()
                : collect(),
            'viewing' => $viewing,
            'viewingPassages' => $viewing
                ? collect(app(HighlightPicker::class)->passages($viewing->chunks->map(fn (NotebookChunk $chunk): array => [
                    'id' => $chunk->id,
                    'position' => $chunk->position,
                    'content' => (string) $chunk->content,
                ])->all()))
                : collect(),
            'maxSources' => NotebookConfig::maxSources(),
            'maxFileMegabytes' => NotebookConfig::maxFileMegabytes(),
            'webConfigured' => app(WebSourceFinder::class)->configured(),
        ]);
    }
}
