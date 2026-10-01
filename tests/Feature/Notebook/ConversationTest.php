<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Chat;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookConversation;
use App\Models\NotebookMessage;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->notebook = Notebook::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher);

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

    private function fakeAnswer(string $content = 'Trả lời.'): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $content]]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);
    }

    private function ask(string $question): void
    {
        $this->fakeAnswer();

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', $question)
            ->call('send')
            ->call('streamAnswer');
    }

    public function test_opening_chat_creates_a_default_conversation(): void
    {
        $this->assertSame(0, NotebookConversation::query()->count());

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id]);

        $this->assertSame(1, NotebookConversation::query()->where('notebook_id', $this->notebook->id)->count());
    }

    public function test_old_messages_without_a_conversation_are_adopted(): void
    {
        NotebookMessage::create(['notebook_id' => $this->notebook->id, 'role' => 'user', 'content' => 'Tin cũ']);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id]);

        $conversation = NotebookConversation::query()->where('notebook_id', $this->notebook->id)->firstOrFail();

        $this->assertSame($conversation->id, NotebookMessage::query()->firstOrFail()->conversation_id);
    }

    public function test_first_question_names_the_conversation(): void
    {
        $this->ask('Cauchy là bất đẳng thức gì?');

        $conversation = NotebookConversation::query()->where('notebook_id', $this->notebook->id)->firstOrFail();

        $this->assertSame('Cauchy là bất đẳng thức gì?', $conversation->title);
    }

    public function test_two_threads_keep_separate_histories(): void
    {
        $this->ask('Câu hỏi luồng một');

        $firstId = NotebookConversation::query()->where('notebook_id', $this->notebook->id)->firstOrFail()->id;

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])->call('newConversation');

        $secondId = NotebookConversation::query()
            ->where('notebook_id', $this->notebook->id)
            ->whereKeyNot($firstId)
            ->firstOrFail()->id;

        $this->fakeAnswer();

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('openConversation', $secondId)
            ->set('prompt', 'Câu hỏi luồng hai')
            ->call('send')
            ->call('streamAnswer');

        // Đổi tên để tên luồng không lẫn với nội dung tin nhắn khi assert.
        NotebookConversation::query()->whereKey($firstId)->update(['title' => 'Chủ đề A']);
        NotebookConversation::query()->whereKey($secondId)->update(['title' => 'Chủ đề B']);

        // Luồng hai chỉ thấy tin của nó.
        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('openConversation', $secondId)
            ->assertSee('Câu hỏi luồng hai')
            ->assertDontSee('Câu hỏi luồng một');

        // Luồng một vẫn nguyên.
        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('openConversation', $firstId)
            ->assertSee('Câu hỏi luồng một')
            ->assertDontSee('Câu hỏi luồng hai');
    }

    public function test_rename_and_delete_conversation(): void
    {
        $this->ask('Câu hỏi đầu');

        $component = Livewire::test(Chat::class, ['notebookId' => $this->notebook->id]);
        $firstId = NotebookConversation::query()->firstOrFail()->id;

        $component->call('newConversation');

        $secondId = NotebookConversation::query()->whereKeyNot($firstId)->firstOrFail()->id;

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('startRename', $secondId)
            ->set('renamingTitle', 'Ôn thi giữa kỳ')
            ->call('saveRename');

        $this->assertSame('Ôn thi giữa kỳ', NotebookConversation::query()->findOrFail($secondId)->title);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('deleteConversation', $secondId);

        $this->assertNull(NotebookConversation::query()->find($secondId));
        $this->assertSame(1, NotebookConversation::query()->count());
    }

    public function test_deleting_the_last_conversation_creates_a_fresh_one(): void
    {
        $this->ask('Câu hỏi duy nhất');

        $onlyId = NotebookConversation::query()->firstOrFail()->id;

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->call('deleteConversation', $onlyId);

        $this->assertSame(1, NotebookConversation::query()->count());
        $this->assertSame(0, NotebookMessage::query()->count());
    }

    public function test_clear_only_empties_the_current_thread(): void
    {
        $this->ask('Luồng một');

        $firstId = NotebookConversation::query()->firstOrFail()->id;

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])->call('newConversation');

        $this->fakeAnswer();

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])
            ->set('prompt', 'Luồng hai')
            ->call('send')
            ->call('streamAnswer')
            ->call('clear');

        $this->assertSame(0, NotebookMessage::query()->where('conversation_id', NotebookConversation::query()->latest('id')->firstOrFail()->id)->count());
        $this->assertGreaterThan(0, NotebookMessage::query()->where('conversation_id', $firstId)->count());
    }

    public function test_other_teacher_cannot_open_the_conversation(): void
    {
        $this->ask('Câu hỏi riêng tư');

        $conversationId = NotebookConversation::query()->firstOrFail()->id;

        $other = User::factory()->teacher($this->subject)->create();
        $this->actingAs($other);

        Livewire::test(Chat::class, ['notebookId' => $this->notebook->id])->assertForbidden();
    }
}
