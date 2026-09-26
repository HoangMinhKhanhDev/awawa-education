<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Jobs\GenerateArtifact;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\ArtifactGenerator;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BackgroundGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->subject->features()->where('feature', 'ai_tools')->update(['is_enabled' => true]);
        $this->subject->forgetFeatureCache();

        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->notebook = Notebook::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher);

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

    private function fakeDocumentText(string $text = 'Nội dung tài liệu đã soạn.'): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $text]]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);
    }

    private function studio(): Testable
    {
        return Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value);
    }

    public function test_generating_creates_a_pending_artifact_and_dispatches_the_job(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->studio()->call('generate')->assertSet('generating', true);

        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('generating', $artifact->status);
        $this->assertStringContainsString('đang soạn', $artifact->title);
        $this->assertArrayHasKey('_generation', $artifact->payload);

        Queue::assertPushed(GenerateArtifact::class, fn (GenerateArtifact $job): bool => $job->artifactId === $artifact->id);
    }

    public function test_the_job_writes_the_result_into_the_artifact(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Nội dung tài liệu đã soạn.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();
        $this->assertSame('generating', $artifact->status);

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('Nội dung tài liệu đã soạn.', $artifact->text_content);
        $this->assertArrayNotHasKey('_error', $artifact->payload);
    }

    public function test_poll_finishes_a_pending_artifact_when_no_queue_worker_runs(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Xong rồi mới có nội dung.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();
        $this->assertSame('generating', $artifact->status);

        NotebookArtifact::query()
            ->whereKey($artifact->id)
            ->update(['updated_at' => now()->subMinutes(1)]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('poll')
            ->assertSet('generating', false)
            ->assertSee('Đã soạn xong');

        $this->assertSame('draft', $artifact->fresh()->status);
    }

    public function test_a_notice_is_shown_once_after_the_user_comes_back(): void
    {
        Queue::fake();
        $this->fakeDocumentText('Nội dung hoàn tất.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $title = $artifact->fresh()->title;
        $this->assertStringContainsString('Tài liệu / tóm tắt', $title);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSee('Đã soạn xong: '.$title);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertDontSee('Đã soạn xong: '.$title);
    }

    public function test_a_failed_generation_is_recorded_for_retry(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'máy chủ bận']], 500),
        ]);

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('failed', $artifact->status);
        $this->assertStringContainsString('máy chủ bận', (string) $artifact->failedReason());
    }

    public function test_publish_and_delete_are_blocked_while_generating(): void
    {
        Queue::fake();
        $this->fakeDocumentText();

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();
        $this->assertSame('generating', $artifact->status);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('publish', $artifact->id)
            ->assertSet('error', 'Nội dung này đang được soạn, vui lòng đợi xong rồi xuất bản.');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('delete', $artifact->id)
            ->assertSet('error', 'Nội dung đang được soạn, không thể xóa lúc này.');

        $this->assertDatabaseHas('notebook_artifacts', ['id' => $artifact->id]);
    }

    public function test_regenerate_puts_the_artifact_back_into_background_generation(): void
    {
        $this->fakeDocumentText('Nội dung mới.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));
        $artifact->refresh();

        Queue::fake();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('regenerate', $artifact->id)
            ->assertSet('generating', true);

        $artifact->refresh();

        $this->assertSame('generating', $artifact->status);
        $this->assertNull($artifact->text_content);
        $this->assertArrayHasKey('_generation', $artifact->payload);
    }

    public function test_content_can_be_generated_without_any_instruction(): void
    {
        $this->fakeDocumentText('AI tự soạn từ nguồn.');

        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Nội dung nguồn về bất đẳng thức.');

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->set('instruction', '')
            ->call('generate')
            ->assertHasNoErrors();

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $artifact->refresh();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('AI tự soạn từ nguồn.', $artifact->text_content);

        Http::assertSent(function (HttpRequest $request): bool {
            $body = $request->data();

            if (! is_array($body)) {
                return false;
            }

            $userMessage = (string) ($body['messages'][1]['content'] ?? '');

            $this->assertStringContainsString('dựa trên các nguồn đang được bật', $userMessage);
            $this->assertStringNotContainsString('Nhiệm vụ:', $userMessage);

            return true;
        });
    }

    public function test_creating_without_any_source_is_refused(): void
    {
        $this->fakeDocumentText();

        $this->studio()->call('generate')->assertSet('error', null);

        $this->assertSame(1, NotebookArtifact::query()->count());
    }

    public function test_a_new_name_is_used_when_no_instruction_is_given(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => 'Nội dung không có tiêu đề trong JSON.']]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);

        app(SourceIngestor::class)->fromText($this->notebook, 'Chuyên đề', 'Nội dung nguồn.');

        $this->studio()->call('generate');

        $artifact = NotebookArtifact::query()->firstOrFail();

        (new GenerateArtifact($artifact->id))->handle(app(ArtifactGenerator::class));

        $this->assertSame('Tài liệu / tóm tắt - '.$this->subject->name, $artifact->fresh()->title);
    }
}
