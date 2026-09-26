<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Enums\MapVisibility;
use App\Enums\SubjectFeature;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Document;
use App\Models\KnowledgeMap;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudioTest extends TestCase
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
        $this->subject->features()->where('feature', SubjectFeature::AiTools->value)->update(['is_enabled' => true]);
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

    private function fakeJson(array $payload): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 42],
            ], 200),
        ]);
    }

    private function fakeText(string $text): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => $text]]],
                'usage' => ['total_tokens' => 30],
            ], 200),
        ]);
    }

    public function test_generate_questions_creates_draft_artifact(): void
    {
        $this->fakeJson([[
            'type' => 'multiple_choice',
            'content' => 'Giá trị của 2 + 2 là?',
            'options' => [
                ['content' => '4', 'is_correct' => true],
                ['content' => '5', 'is_correct' => false],
            ],
            'answer' => '4',
            'difficulty' => 'easy',
            'points' => 1,
            'topic' => 'Số học',
        ]]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openForm', ArtifactType::Questions->value)
            ->set('instruction', 'Soạn câu hỏi số học')
            ->call('generate')
            ->assertHasNoErrors();

        $artifact = NotebookArtifact::query()->where('notebook_id', $this->notebook->id)->firstOrFail();

        $this->assertSame('draft', $artifact->status);
        $this->assertSame('questions', $artifact->type);
        $this->assertCount(1, $artifact->payload['items']);
    }

    public function test_publish_questions_inserts_into_bank(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'questions',
            'title' => 'Câu hỏi AI',
            'status' => 'draft',
            'payload' => ['items' => [[
                'type' => 'multiple_choice',
                'content' => '2 + 2?',
                'options' => [
                    ['content' => '4', 'is_correct' => true],
                    ['content' => '5', 'is_correct' => false],
                ],
                'answer' => '4',
                'difficulty' => 'easy',
                'points' => 1,
            ]]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('publish', $artifact->id);

        $this->assertSame(1, Question::query()->where('subject_id', $this->subject->id)->count());
        $this->assertSame('published', $artifact->fresh()->status);

        $question = Question::query()->first();
        $this->assertCount(2, $question->options);
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
    }

    public function test_publish_document_creates_public_document(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'document',
            'title' => 'Tóm tắt chuyên đề',
            'status' => 'draft',
            'text_content' => "# Tóm tắt\nNội dung...",
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->set('publishPublic', true)
            ->call('publish', $artifact->id);

        $document = Document::query()->firstOrFail();

        $this->assertTrue($document->is_public);
        $this->assertSame($this->subject->id, $document->subject_id);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertSame('published', $artifact->fresh()->status);
    }

    public function test_publish_mindmap_creates_knowledge_map(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'mindmap',
            'title' => 'Sơ đồ bất đẳng thức',
            'status' => 'draft',
            'payload' => ['nodes' => [
                ['id' => 'n1', 'label' => 'Bất đẳng thức', 'parent' => null],
                ['id' => 'n2', 'label' => 'Cauchy', 'parent' => 'n1'],
            ]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('publish', $artifact->id);

        $map = KnowledgeMap::query()->firstOrFail();

        $this->assertSame(MapVisibility::Private, $map->visibility);
        $this->assertSame(1, $map->current_version);
        $this->assertNotEmpty($map->latestVersion->scene['elements']);
        $this->assertSame('published', $artifact->fresh()->status);
    }

    public function test_type_hidden_when_subject_feature_disabled(): void
    {
        $this->subject->features()->where('feature', SubjectFeature::QuestionBank->value)->update(['is_enabled' => false]);
        $this->subject->forgetFeatureCache();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openForm', ArtifactType::Questions->value)
            ->assertSet('formType', null);
    }

    public function test_other_teacher_cannot_use_studio(): void
    {
        $other = User::factory()->teacher($this->subject)->create();
        $this->actingAs($other);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->assertForbidden();
    }
}
