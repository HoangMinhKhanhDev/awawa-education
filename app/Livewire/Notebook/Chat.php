<?php

namespace App\Livewire\Notebook;

use App\Enums\AiPurpose;
use App\Models\Notebook;
use App\Models\NotebookConversation;
use App\Models\NotebookMessage;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Notebook\ChatAnswerRenderer;
use App\Services\Notebook\PromptComposer;
use App\Support\NotebookConfig;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Chat extends Component
{
    #[Locked]
    public int $notebookId;

    public string $prompt = '';

    public bool $streaming = false;

    public ?string $error = null;

    public bool $truncated = false;

    /** @var array<int, int|string> */
    public array $selectedSourceIds = [];

    public string $selectedProviderKey = '';

    public string $selectedModel = '';

    /** @var array<int, array{id: string, name: string, free: bool}> */
    public array $availableModels = [];

    public ?string $modelError = null;

    public string $focusSourceTitle = '';

    public int $visibleCount = 0;

    public ?int $conversationId = null;

    public ?int $renamingId = null;

    public string $renamingTitle = '';

    /** Memo trong một request: render() cũ gọi notebook() nhiều lần. */
    protected ?Notebook $memoNotebook = null;

    public function mount(int $notebookId, AiManager $ai): void
    {
        $this->notebookId = $notebookId;
        $this->guard();

        $notebook = $this->notebook();
        $this->selectedSourceIds = $notebook->enabledSourceIds();
        $settings = $notebook->settings ?? [];
        $this->selectedProviderKey = (string) ($settings['ai_provider'] ?? $ai->defaultProviderKey() ?? '');
        $this->selectedModel = (string) ($settings['ai_model'] ?? $ai->defaultModelFor($this->selectedProviderKey) ?? '');
        $this->visibleCount = $this->renderLimit();
        $this->conversationId = $this->ensureConversation()->id;

        if ($this->selectedModel !== '') {
            $this->availableModels = [[
                'id' => $this->selectedModel,
                'name' => $this->selectedModel,
                'free' => str_ends_with($this->selectedModel, ':free'),
            ]];
        }
    }

    protected function notebook(): Notebook
    {
        if ($this->memoNotebook === null) {
            $this->memoNotebook = Notebook::query()->findOrFail($this->notebookId);
        }

        return $this->memoNotebook;
    }

    protected function guard(): void
    {
        abort_unless($this->notebook()->isOwnedBy(auth()->user()), 403);
    }

    /**
     * Luồng đang mở. Chưa có thì tạo mới và gom tin nhắn cũ chưa thuộc luồng
     * nào vào đó, nên dữ liệu trước khi có tính năng này không bị mất.
     */
    protected function ensureConversation(): NotebookConversation
    {
        $notebook = $this->notebook();

        $conversation = $notebook->conversations()->first();

        if ($conversation === null) {
            $conversation = NotebookConversation::create([
                'notebook_id' => $notebook->id,
                'user_id' => auth()->id(),
                'title' => 'Cuộc trò chuyện mới',
            ]);

            $notebook->messages()->whereNull('conversation_id')->update(['conversation_id' => $conversation->id]);
        }

        return $conversation;
    }

    protected function conversation(): NotebookConversation
    {
        $conversation = $this->notebook()->conversations()->find($this->conversationId);

        abort_unless($conversation !== null && $conversation->isOwnedBy(auth()->user()), 404);

        return $conversation;
    }

    protected function conversationMessages(): HasMany
    {
        return $this->conversation()->messages();
    }

    /**
     * Số tin nhắn gần nhất vẽ lên màn hình. Tin cũ hơn vẫn trong database và vẫn
     * gửi cho AI, chỉ không render để mỗi request không parse lại Markdown cả
     * cuộc trò chuyện dài.
     */
    protected function renderLimit(): int
    {
        return max(10, (int) config('awawa.notebook.chat_render_limit', 50));
    }

    public function loadMore(): void
    {
        $this->guard();

        $this->visibleCount += $this->renderLimit();
    }

    public function askSuggestion(string $text): void
    {
        $this->guard();

        if ($this->streaming) {
            return;
        }

        $this->prompt = $text;
        $this->send();
    }

    protected function hitChatLimiter(): bool
    {
        $key = 'notebook-chat:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->error = 'Bạn đang hỏi quá nhanh. Vui lòng chờ 1 phút rồi thử lại.';

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function send(): void
    {
        $this->guard();

        if (! $this->hitChatLimiter()) {
            return;
        }

        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'prompt' => ['required', 'string', 'min:2', 'max:4000'],
        ], [
            'prompt.required' => 'Nhập câu hỏi hoặc yêu cầu.',
        ]);

        $enabledSourceIds = $this->notebook()->enabledSourceIds();
        $selectedSourceIds = array_values(array_intersect($enabledSourceIds, $this->normalizedSourceIds()));
        $this->selectedSourceIds = $selectedSourceIds;

        $conversation = $this->conversation();

        NotebookMessage::create([
            'notebook_id' => $this->notebookId,
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $this->prompt,
            'source_ids' => $selectedSourceIds,
        ]);

        // Luồng mới chưa đặt tên thì lấy câu hỏi đầu làm tên.
        if ($conversation->title === 'Cuộc trò chuyện mới') {
            $conversation->forceFill(['title' => mb_substr($this->prompt, 0, 60)])->save();
        } else {
            $conversation->touch();
        }

        $this->prompt = '';
        $this->truncated = false;
        $this->streaming = true;

        // Tách request: hiện tin nhắn của người dùng ngay, rồi mới stream câu trả lời.
        $this->js('$wire.streamAnswer()');
        $this->js('window.__awawaScrollChat && window.__awawaScrollChat()');
    }

    public function streamAnswer(PromptComposer $composer, AiManager $ai): void
    {
        $this->guard();

        $last = $this->conversationMessages()->reorder('id', 'desc')->first();

        if ($last === null || $last->role !== 'user') {
            $this->streaming = false;

            return;
        }

        $history = $this->conversationMessages()
            ->where('id', '<', $last->id)
            ->reorder('id', 'desc')
            ->limit(NotebookConfig::historyMessages())
            ->get()
            ->reverse()
            ->map(fn (NotebookMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->values()
            ->all();

        $sourceIds = $last->source_ids ?? $this->notebook()->enabledSourceIds();
        $context = $composer->compose($this->notebook(), (string) $last->content, $history, $sourceIds);
        $this->truncated = $context['truncated'];

        $pinned = $ai->pinnedSelection($this->selectedProviderKey, $this->selectedModel);

        $options = [
            'purpose' => AiPurpose::Chat,
            'subject_id' => app(SubjectContext::class)->id() ?? $this->notebook()->subject_id,
            'user_id' => auth()->id(),
            'provider_key' => $pinned['provider_key'],
            'model' => $pinned['model'],
            'temperature' => 0.4,

            // Câu hỏi bám nguồn hiếm khi cần hơn 1.200 token; để cao hơn chỉ khiến
            // thời gian chờ dài thêm mà không thêm thông tin.
            'max_tokens' => 1200,
        ];

        $emittedDelta = false;

        try {
            if (NotebookConfig::streamEnabled()) {
                $result = $ai->chatStream($context['messages'], $options, function (string $delta) use (&$emittedDelta): void {
                    $emittedDelta = true;
                    $this->stream(to: 'answer', content: $delta);
                });
            } else {
                $result = $ai->chat($context['messages'], $options);
                $this->stream(to: 'answer', content: $result->text);
            }
        } catch (AiException $streamException) {
            // Lỗi hạn mức thì không gọi lại ngay: mỗi lần gọi thêm chỉ làm nặng thêm giới hạn.
            if (! NotebookConfig::streamEnabled() || $emittedDelta || ! $streamException->allowsFallback()) {
                $this->error = $streamException->getMessage();
                $this->streaming = false;

                return;
            }

            try {
                $result = $ai->chat($context['messages'], $options);
                $this->stream(to: 'answer', content: $result->text);
            } catch (AiException $fallbackException) {
                $this->error = $fallbackException->getMessage();
                $this->streaming = false;

                return;
            }
        }

        $citations = $this->usedCitations($result->text, $context['citations']);

        NotebookMessage::create([
            'notebook_id' => $this->notebookId,
            'conversation_id' => $this->conversation()->id,
            'user_id' => auth()->id(),
            'role' => 'assistant',
            'content' => $result->text,
            'rendered_html' => app(ChatAnswerRenderer::class)->render($result->text, $citations),
            'source_ids' => $sourceIds,
            'citations' => $citations,
            'provider_key' => $result->providerKey,
            'model' => $result->model,
            'tokens' => $result->totalTokens(),
        ]);

        $this->streaming = false;
        $this->js('window.__awawaScrollChat && window.__awawaScrollChat()');
    }

    public function retry(): void
    {
        $this->guard();

        $last = $this->conversationMessages()->reorder('id', 'desc')->first();

        if ($this->streaming || $last === null || $last->role !== 'user') {
            return;
        }

        $this->error = null;
        $this->truncated = false;
        $this->streaming = true;
        $this->js('$wire.streamAnswer()');
        $this->js('window.__awawaScrollChat && window.__awawaScrollChat()');
    }

    public function providerChanged(AiManager $ai): void
    {
        $provider = collect($ai->chatProviders())->firstWhere('key', $this->selectedProviderKey);

        if ($provider === null) {
            $this->selectedProviderKey = $ai->defaultProviderKey() ?? '';
            $this->modelError = 'Nhà cung cấp AI này hiện không khả dụng.';

            return;
        }

        $this->selectedModel = $provider['model'];
        $this->availableModels = [[
            'id' => $provider['model'],
            'name' => $provider['model'],
            'free' => str_ends_with($provider['model'], ':free'),
        ]];
        $this->modelError = null;
        $this->persistModelChoice();
        $this->loadModels($ai);
    }

    public function loadModels(AiManager $ai): void
    {
        $this->fetchModels($ai, false);
    }

    public function refreshModels(AiManager $ai): void
    {
        $this->fetchModels($ai, true);
    }

    protected function fetchModels(AiManager $ai, bool $refresh): void
    {
        if ($this->selectedProviderKey === '') {
            return;
        }

        try {
            $this->availableModels = $ai->modelsForProvider($this->selectedProviderKey, $refresh);
            $this->modelError = null;

            if (! collect($this->availableModels)->contains('id', $this->selectedModel)) {
                $this->selectedModel = $this->availableModels[0]['id'] ?? $this->selectedModel;
                $this->persistModelChoice();
            }
        } catch (AiException $exception) {
            $this->modelError = 'Không tải được danh sách model: '.$exception->getMessage();
        }
    }

    public function saveSelectedModel(AiManager $ai): void
    {
        try {
            $models = $ai->modelsForProvider($this->selectedProviderKey);
        } catch (AiException $exception) {
            $this->modelError = 'Không xác minh được model: '.$exception->getMessage();

            return;
        }

        if (! collect($models)->contains('id', $this->selectedModel)) {
            $this->modelError = 'Model đã chọn không còn khả dụng. Hãy tải lại danh sách model.';

            return;
        }

        $this->availableModels = $models;
        $this->modelError = null;
        $this->persistModelChoice();
    }

    protected function persistModelChoice(): void
    {
        $notebook = $this->notebook();
        $settings = $notebook->settings ?? [];
        $settings['ai_provider'] = $this->selectedProviderKey;
        $settings['ai_model'] = $this->selectedModel;
        $notebook->forceFill(['settings' => $settings])->save();
    }

    /**
     * @return array<int, int>
     */
    protected function normalizedSourceIds(): array
    {
        $ids = array_filter($this->selectedSourceIds, fn (mixed $id): bool => is_int($id)
            || (is_string($id) && ctype_digit($id)));

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function regenerate(int $assistantId): void
    {
        $this->guard();

        if ($this->streaming) {
            return;
        }

        $last = $this->conversationMessages()->reorder('id', 'desc')->first();

        if ($last === null || $last->id !== $assistantId || $last->role !== 'assistant') {
            return;
        }

        $previous = $this->conversationMessages()->where('id', '<', $last->id)->reorder('id', 'desc')->first();

        if ($previous === null || $previous->role !== 'user') {
            return;
        }

        $last->delete();
        $this->error = null;
        $this->truncated = false;
        $this->streaming = true;
        $this->js('$wire.streamAnswer()');
        $this->js('window.__awawaScrollChat && window.__awawaScrollChat()');
    }

    #[On('notebook-source-added')]
    public function syncAddedSource(?int $sourceId = null): void
    {
        $enabledIds = $this->notebook()->enabledSourceIds();
        $selectedIds = array_values(array_intersect($enabledIds, $this->normalizedSourceIds()));

        if ($sourceId !== null && in_array($sourceId, $enabledIds, true)) {
            $selectedIds[] = $sourceId;
        }

        $this->selectedSourceIds = array_values(array_unique($selectedIds));
    }

    #[On('notebook-ask-source')]
    public function focusSource(int $sourceId, string $sourceTitle = ''): void
    {
        $this->guard();

        $enabledSourceIds = $this->notebook()->enabledSourceIds();
        abort_unless(in_array($sourceId, $enabledSourceIds, true), 404);

        $this->selectedSourceIds = [$sourceId];
        $this->focusSourceTitle = $sourceTitle;
        $this->js("document.getElementById('notebook-chat-prompt')?.focus()");
    }

    #[On('notebook-sources-changed')]
    public function syncChangedSources(): void
    {
        $enabledIds = $this->notebook()->enabledSourceIds();
        $this->selectedSourceIds = array_values(array_intersect($enabledIds, $this->normalizedSourceIds()));
    }

    public function openCitation(int $sourceId, int $chunkId): void
    {
        $this->guard();
        $source = $this->notebook()->sources()->findOrFail($sourceId);
        abort_unless($source->chunks()->whereKey($chunkId)->exists(), 404);
        $this->dispatch('notebook-open-cited-source', sourceId: $source->id, chunkId: $chunkId);
    }

    /**
     * Chỉ giữ trích dẫn thực sự được dùng trong câu trả lời.
     *
     * @param  array<int, array<string, mixed>>  $all
     * @return array<int, array<string, mixed>>
     */
    protected function usedCitations(string $text, array $all): array
    {
        preg_match_all('/\[(\d+)\]/', $text, $matches);

        $used = array_unique(array_map('intval', $matches[1] ?? []));

        $citations = [];

        foreach ($used as $index) {
            if (isset($all[$index])) {
                $citations[] = $all[$index];
            }
        }

        return $citations;
    }

    public function clear(): void
    {
        $this->guard();

        $this->conversationMessages()->delete();
        $this->error = null;
        $this->truncated = false;
    }

    public function newConversation(): void
    {
        $this->guard();

        $conversation = NotebookConversation::create([
            'notebook_id' => $this->notebookId,
            'user_id' => auth()->id(),
            'title' => 'Cuộc trò chuyện mới',
        ]);

        $this->openConversation($conversation->id);
    }

    public function openConversation(int $conversationId): void
    {
        $this->guard();

        $conversation = $this->notebook()->conversations()->find($conversationId);

        abort_unless($conversation !== null && $conversation->isOwnedBy(auth()->user()), 404);

        $this->conversationId = $conversation->id;
        $this->visibleCount = $this->renderLimit();
        $this->error = null;
        $this->truncated = false;
        $this->streaming = false;
        $this->renamingId = null;
        $this->renamingTitle = '';
    }

    public function startRename(int $conversationId): void
    {
        $this->guard();

        $conversation = $this->notebook()->conversations()->findOrFail($conversationId);

        abort_unless($conversation->isOwnedBy(auth()->user()), 403);

        $this->renamingId = $conversation->id;
        $this->renamingTitle = $conversation->title;
    }

    public function saveRename(): void
    {
        $this->guard();

        $conversation = $this->notebook()->conversations()->findOrFail($this->renamingId ?? 0);

        abort_unless($conversation->isOwnedBy(auth()->user()), 403);

        $this->validate(['renamingTitle' => ['required', 'string', 'max:200']], [
            'renamingTitle.required' => 'Nhập tên cuộc trò chuyện.',
        ]);

        $conversation->forceFill(['title' => $this->renamingTitle])->save();

        $this->renamingId = null;
        $this->renamingTitle = '';
    }

    public function cancelRename(): void
    {
        $this->renamingId = null;
        $this->renamingTitle = '';
    }

    public function deleteConversation(int $conversationId): void
    {
        $this->guard();

        $conversation = $this->notebook()->conversations()->findOrFail($conversationId);

        abort_unless($conversation->isOwnedBy(auth()->user()), 403);

        $conversation->messages()->delete();
        $conversation->delete();

        $this->conversationId = $this->ensureConversation()->id;
        $this->visibleCount = $this->renderLimit();
        $this->error = null;
        $this->truncated = false;
        $this->streaming = false;
    }

    public function render(): View
    {
        $notebook = $this->notebook()->loadMissing('subject');

        $total = $this->conversationMessages()->count();

        $messages = $this->conversationMessages()
            ->reorder('id', 'desc')
            ->limit(max(1, $this->visibleCount))
            ->get()
            ->reverse()
            ->values();

        $renderer = app(ChatAnswerRenderer::class);
        $answers = [];

        foreach ($messages as $message) {
            if ($message->role === 'user') {
                continue;
            }

            $answers[$message->id] = $this->renderedAnswer($message, $renderer);
        }

        return view('livewire.notebook.chat', [
            'messages' => $messages,
            'answers' => $answers,
            'notebook' => $notebook,
            'sources' => $notebook->sources()->where('is_enabled', true)->where('status', 'ready')->get(),
            'sourceCount' => count($notebook->enabledSourceIds()),
            'providers' => app(AiManager::class)->chatProviders(),
            'hasOlder' => $total > $messages->count(),
            'totalMessages' => $total,
            'conversations' => $notebook->conversations()->get(['id', 'title', 'updated_at']),
            'currentConversation' => $this->notebook()->conversations()->find($this->conversationId),
        ]);
    }

    /**
     * HTML của tin nhắn không đổi sau khi tạo, nên tính một lần rồi dùng lại.
     * Tin cũ chưa có `rendered_html` thì tính bù rồi ghi lại, giữ nguyên
     * `updated_at` vì cột đó không phải dữ liệu hiển thị.
     */
    protected function renderedAnswer(NotebookMessage $message, ChatAnswerRenderer $renderer): string
    {
        if (filled($message->rendered_html)) {
            return $message->rendered_html;
        }

        $html = $renderer->render((string) $message->content, $message->citationList());

        $message->timestamps = false;
        $message->forceFill(['rendered_html' => $html])->save();
        $message->timestamps = true;

        return $html;
    }
}
