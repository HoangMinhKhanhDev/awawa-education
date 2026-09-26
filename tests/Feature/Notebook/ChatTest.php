<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Chat;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookMessage;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
