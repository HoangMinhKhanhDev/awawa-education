<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Chat;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookMessage;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Notebook\PromptComposer;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->notebook = Notebook::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher);

        // Tắt streaming trong test để dùng nhánh chat() thường.
        NotebookSetting::set('ai_stream', '0');

        AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'test-key',
            'default_model' => 'openrouter/free',
            'is_enabled' => true,
            'is_default' => true,
        ]);
    }

    private function fakeAnswer(string $content): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $content]]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);
    }

    public function test_send_creates_user_message(): void
    {
        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Tóm tắt nguồn')
            ->call('send')
            ->assertSet('streaming', true);

        $this->assertDatabaseHas('notebook_messages', [
            'notebook_id' => $this->notebook->id,
            'role' => 'user',
            'content' => 'Tóm tắt nguồn',
        ]);
    }

    public function test_stream_answer_persists_assistant_message_with_citations(): void
    {
        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Bất đẳng thức Cauchy được dùng để chứng minh.');

        $this->fakeAnswer('Cauchy là bất đẳng thức quan trọng [1].');

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Cauchy là gì?')
            ->call('send');

        $component->call('streamAnswer');

        $assistant = NotebookMessage::query()
            ->where('notebook_id', $this->notebook->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertStringContainsString('[1]', $assistant->content);
        $this->assertNotEmpty($assistant->citations);
        $this->assertSame('Chuyên đề', $assistant->citations[0]['source_title']);
        $this->assertSame(20, $assistant->tokens);
        $this->assertFalse($component->get('streaming'));
    }

    public function test_answer_is_rendered_as_block_markdown_with_citation_buttons(): void
    {
        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Bất đẳng thức Cauchy được dùng để chứng minh.');

        $this->fakeAnswer("**Cấu trúc đề thi:**\n*   **Phần I:** Trắc nghiệm 40 câu [1]\n*   **Phần II:** Tự luận [1]");

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Cấu trúc đề thi?')
            ->call('send');

        $component->call('streamAnswer');

        $assistant = NotebookMessage::query()
            ->where('notebook_id', $this->notebook->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $component->assertSee('<ul>', escape: false)
            ->assertSee('<strong>Cấu trúc đề thi:</strong>', escape: false)
            ->assertSee('openCitation(', escape: false)
            ->assertDontSee('*   ', escape: false);

        $this->assertStringNotContainsString('[1]', $component->html());
        $this->assertStringContainsString('notebook-answer', $component->html());
        $this->assertSame('*   **Phần I:** Trắc nghiệm 40 câu [1]', trim(explode("\n", $assistant->content)[1]));
    }

    public function test_prompt_includes_the_source_index(): void
    {
        $ingestor = app(SourceIngestor::class);
        $ingestor->fromText($this->notebook, 'Lịch sử Việt Nam', 'Chiến thắng Bạch Đằng là dấu mốc lịch sử.');
        $ingestor->fromText($this->notebook, 'Quang học', 'Photon truyền trong môi trường chân không.');

        $system = app(PromptComposer::class)
            ->compose($this->notebook, 'Photon truyền thế nào?')['messages'][0]['content'];

        $this->assertStringContainsString('=== DANH MÁCH NGUỒN ===', $system);
        $this->assertMatchesRegularExpression('/\[1\] (Lịch sử Việt Nam|Quang học)/', $system);
    }

    public function test_disabled_sources_are_not_used(): void
    {
        $source = app(SourceIngestor::class)->fromText($this->notebook, 'Nguồn tắt', 'Nội dung không dùng.');
        $source->forceFill(['is_enabled' => false])->save();

        $this->fakeAnswer('Trả lời không có nguồn.');

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Hỏi')
            ->call('send');

        $component->call('streamAnswer');

        $assistant = NotebookMessage::query()->where('role', 'assistant')->firstOrFail();

        $this->assertSame([], $assistant->citations);
    }

    public function test_prompt_prioritizes_relevant_source_chunks_and_reports_omitted_context(): void
    {
        config()->set('awawa.notebook.max_context_chunks', 1);

        $ingestor = app(SourceIngestor::class);
        $ingestor->fromText($this->notebook, 'Lịch sử Việt Nam', 'Chiến thắng Bạch Đằng là một dấu mốc lịch sử quan trọng.');
        $ingestor->fromText($this->notebook, 'Quang học', 'Photon truyền trong môi trường chân không với tốc độ ánh sáng.');

        $context = app(PromptComposer::class)->compose($this->notebook, 'Photon truyền trong môi trường chân không thế nào?');

        $this->assertCount(1, $context['citations']);
        $this->assertSame('Quang học', $context['citations'][1]['source_title']);
        $this->assertTrue($context['truncated']);
        $this->assertStringContainsString('Photon truyền', $context['messages'][0]['content']);
        $this->assertStringNotContainsString('Bạch Đằng', $context['messages'][0]['content']);
    }

    public function test_chat_can_retry_a_failed_provider_request(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push(['error' => ['message' => 'server overloaded']], 503)
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Đây là phần trả lời thử lại.']]],
                    'usage' => ['total_tokens' => 12],
                ], 200),
        ]);

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Tóm tắt nội dung')
            ->call('send')
            ->call('streamAnswer')
            ->assertSet('streaming', false)
            ->assertSee('server overloaded');

        $component->call('retry')->assertSet('streaming', true)->call('streamAnswer');

        $this->assertDatabaseHas('notebook_messages', [
            'notebook_id' => $this->notebook->id,
            'role' => 'assistant',
            'content' => 'Đây là phần trả lời thử lại.',
        ]);
    }

    public function test_chat_falls_back_to_regular_response_when_stream_has_no_content(): void
    {
        NotebookSetting::set('ai_stream', '1');
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push('', 200, ['Content-Type' => 'text/event-stream'])
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Câu trả lời dự phòng thành công.']]],
                    'usage' => ['total_tokens' => 8],
                ], 200),
        ]);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Giải thích khái niệm này')
            ->call('send')
            ->call('streamAnswer')
            ->assertSet('streaming', false)
            ->assertSet('error', null);

        $this->assertDatabaseHas('notebook_messages', [
            'notebook_id' => $this->notebook->id,
            'role' => 'assistant',
            'content' => 'Câu trả lời dự phòng thành công.',
        ]);
        Http::assertSentCount(2);
    }

    public function test_teacher_can_select_a_model_from_the_center_chat_bar(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response([
                'data' => [['id' => 'openrouter/test-model', 'name' => 'Test Model']],
            ], 200),
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'openrouter/test-model',
                'choices' => [['message' => ['content' => 'Đã trả lời bằng model đã chọn.']]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->assertSee('Model AI')
            ->call('loadModels')
            ->assertSet('availableModels.0.id', 'openrouter/test-model')
            ->set('selectedModel', 'openrouter/test-model')
            ->call('saveSelectedModel')
            ->set('prompt', 'Tóm tắt kiến thức')
            ->call('send')
            ->call('streamAnswer');

        $this->assertSame('openrouter/test-model', $this->notebook->fresh()->settings['ai_model']);
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'openrouter/test-model');
    }

    public function test_chat_rejects_a_model_not_returned_by_the_selected_provider(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response([
                'data' => [['id' => 'openrouter/allowed-model', 'name' => 'Allowed Model']],
            ], 200),
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'openrouter/expensive-unlisted-model',
                'choices' => [['message' => ['content' => 'Should not be sent.']]],
            ], 200),
        ]);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('selectedModel', 'openrouter/expensive-unlisted-model')
            ->set('prompt', 'Tạo nội dung')
            ->call('send')
            ->call('streamAnswer')
            ->assertSee('không nằm trong danh sách model khả dụng');

        $this->assertSame(0, NotebookMessage::query()->where('role', 'assistant')->count());
        Http::assertSentCount(1);
    }

    public function test_chat_uses_only_sources_selected_for_that_question(): void
    {
        $ingestor = app(SourceIngestor::class);
        $selected = $ingestor->fromText($this->notebook, 'Selected notes', 'Cauchy inequality is used for this proof.');
        $excluded = $ingestor->fromText($this->notebook, 'Other notes', 'The French Revolution started in 1789.');

        $this->fakeAnswer('Cauchy inequality helps prove the result [1].');

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('selectedSourceIds', [$selected->id])
            ->set('prompt', 'Explain Cauchy inequality')
            ->call('send')
            ->call('streamAnswer');

        $userMessage = NotebookMessage::query()->where('role', 'user')->firstOrFail();
        $assistantMessage = NotebookMessage::query()->where('role', 'assistant')->firstOrFail();

        $this->assertSame([$selected->id], $userMessage->source_ids);
        $this->assertSame([$selected->id], $assistantMessage->source_ids);
        $this->assertNotContains($excluded->id, $assistantMessage->source_ids);
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/chat/completions')
            && str_contains($request['messages'][0]['content'], 'Selected notes')
            && ! str_contains($request['messages'][0]['content'], 'Other notes'));
    }

    public function test_rate_limited_provider_is_not_retried_and_next_message_waits_for_cooldown(): void
    {
        NotebookSetting::set('ai_stream', '1');
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'error' => ['message' => 'You have reached the API rate limit for free users.'],
            ], 429, ['Retry-After' => '30']),
        ]);

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Câu hỏi một')
            ->call('send')
            ->call('streamAnswer')
            ->assertSet('streaming', false)
            ->assertSee('giới hạn lượt gọi');

        $this->assertSame(0, NotebookMessage::query()->where('role', 'assistant')->count());
        Http::assertSentCount(1);

        $component->set('prompt', 'Câu hỏi hai')->call('send')->call('streamAnswer');

        Http::assertSentCount(1);
        $this->assertSame(2, NotebookMessage::query()->where('role', 'user')->count());
        $this->assertNotNull(app(AiManager::class)->rateLimitedFor('openrouter'));
    }

    public function test_teacher_can_regenerate_the_last_assistant_answer(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Câu trả lời lần đầu.']]],
                    'usage' => ['total_tokens' => 8],
                ], 200)
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Câu trả lời đã tạo lại.']]],
                    'usage' => ['total_tokens' => 9],
                ], 200),
        ]);

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Giải thích nội dung')
            ->call('send')
            ->call('streamAnswer');
        $firstAnswer = NotebookMessage::query()->where('role', 'assistant')->firstOrFail();

        $component->call('regenerate', $firstAnswer->id)
            ->assertSet('streaming', true)
            ->call('streamAnswer')
            ->assertSet('streaming', false);

        $this->assertSame(1, NotebookMessage::query()->where('role', 'assistant')->count());
        $this->assertDatabaseHas('notebook_messages', [
            'notebook_id' => $this->notebook->id,
            'role' => 'assistant',
            'content' => 'Câu trả lời đã tạo lại.',
        ]);
    }

    public function test_clear_removes_messages(): void
    {
        NotebookMessage::create(['notebook_id' => $this->notebook->id, 'role' => 'user', 'content' => 'x']);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])->call('clear');

        $this->assertSame(0, NotebookMessage::query()->where('notebook_id', $this->notebook->id)->count());
    }

    public function test_other_teacher_cannot_chat_in_notebook(): void
    {
        $other = User::factory()->teacher($this->subject)->create();

        $this->actingAs($other);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])->assertForbidden();
    }
}
