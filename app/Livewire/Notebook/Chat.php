<?php

namespace App\Livewire\Notebook;

use App\Enums\AiPurpose;
use App\Models\Notebook;
use App\Models\NotebookMessage;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Notebook\PromptComposer;
use App\Support\NotebookConfig;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Chat extends Component
{
    #[Locked]
    public int $notebookId;

    public string $prompt = '';

    public bool $streaming = false;

    public ?string $error = null;

    public bool $truncated = false;

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

    public function send(): void
    {
        $this->guard();
        $this->resetErrorBag();
        $this->error = null;

        $this->validate([
            'prompt' => ['required', 'string', 'min:2', 'max:4000'],
        ], [
            'prompt.required' => 'Nhập câu hỏi hoặc yêu cầu.',
        ]);

        NotebookMessage::create([
            'notebook_id' => $this->notebookId,
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $this->prompt,
        ]);

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

        $last = $this->notebook()->messages()->latest('id')->first();

        if ($last === null || $last->role !== 'user') {
            $this->streaming = false;

            return;
        }

        $history = $this->notebook()
            ->messages()
            ->where('id', '<', $last->id)
            ->latest('id')
            ->limit(NotebookConfig::historyMessages())
            ->get()
            ->reverse()
            ->map(fn (NotebookMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->values()
            ->all();

        $context = $composer->compose($this->notebook(), (string) $last->content, $history);
        $this->truncated = $context['truncated'];

        $options = [
            'purpose' => AiPurpose::Chat,
            'subject_id' => app(SubjectContext::class)->id() ?? $this->notebook()->subject_id,
            'user_id' => auth()->id(),
            'temperature' => 0.4,
            'max_tokens' => 2500,
        ];

        try {
            if (NotebookConfig::streamEnabled()) {
                $result = $ai->chatStream($context['messages'], $options, function (string $delta): void {
                    $this->stream(to: 'answer', content: $delta);
                });
            } else {
                $result = $ai->chat($context['messages'], $options);
                $this->stream(to: 'answer', content: $result->text);
            }
        } catch (AiException $exception) {
            $this->error = $exception->getMessage();
            $this->streaming = false;

            return;
        }

        NotebookMessage::create([
            'notebook_id' => $this->notebookId,
            'user_id' => auth()->id(),
            'role' => 'assistant',
            'content' => $result->text,
            'citations' => $this->usedCitations($result->text, $context['citations']),
            'provider_key' => $result->providerKey,
            'model' => $result->model,
            'tokens' => $result->totalTokens(),
        ]);

        $this->streaming = false;
        $this->js('window.__awawaScrollChat && window.__awawaScrollChat()');
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

        $this->notebook()->messages()->delete();
        $this->error = null;
        $this->truncated = false;
    }

    public function render(): View
    {
        $notebook = $this->notebook()->loadMissing('subject');

        return view('livewire.notebook.chat', [
            'messages' => $notebook->messages()->get(),
            'notebook' => $notebook,
            'sourceCount' => count($notebook->enabledSourceIds()),
        ]);
    }
}
