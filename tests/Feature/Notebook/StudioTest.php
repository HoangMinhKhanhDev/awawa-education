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
            ->call('selectType', ArtifactType::Questions->value)
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

    public function test_text_draft_can_be_edited_before_publishing(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'document',
            'title' => 'Bản nháp sơ',
            'status' => 'draft',
            'text_content' => 'Nội dung cũ.',
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('startEditingPreview')
            ->set('draftTitle', 'Bản nháp đã sửa')
            ->set('draftText', 'Nội dung mới đã sửa tay.')
            ->call('saveDraft')
            ->assertSet('editingPreview', false);

        $artifact->refresh();

        $this->assertSame('Bản nháp đã sửa', $artifact->title);
        $this->assertSame('Nội dung mới đã sửa tay.', $artifact->text_content);
        $this->assertSame('draft', $artifact->status);
    }

    public function test_markdown_preview_formats_headings_and_strips_raw_html(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'document',
            'title' => 'Tài liệu Markdown',
            'status' => 'draft',
            'text_content' => "# Tiêu đề\n\n<script>alert(1)</script>\n\n- Ý một\n- ý hai",
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->assertSee('<h1>Tiêu đề</h1>', escape: false)
            ->assertSee('<ul>', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_question_draft_can_be_edited_and_only_selected_questions_are_published(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'questions',
            'title' => 'Bộ câu hỏi chọn lọc',
            'status' => 'draft',
            'payload' => ['items' => [
                [
                    'type' => 'essay',
                    'content' => 'Câu hỏi số 1',
                    'answer' => 'Trả lời 1',
                    'difficulty' => 'easy',
                    'points' => 1,
                ],
                [
                    'type' => 'essay',
                    'content' => 'Câu hỏi số 2',
                    'answer' => 'Trả lời 2',
                    'difficulty' => 'hard',
                    'points' => 2,
                ],
            ]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('startEditingPreview')
            ->set('draftPayload.items.0.content', 'Câu hỏi số 1 đã sửa')
            ->set('draftPayload.items.1.included', false)
            ->call('saveDraft')
            ->call('publish', $artifact->id)
            ->assertSet('error', null);

        $artifact->refresh();

        $this->assertSame('Câu hỏi số 1 đã sửa', $artifact->payload['items'][0]['content']);

        $question = Question::query()->firstOrFail();

        $this->assertSame('Câu hỏi số 1 đã sửa', $question->content);
        $this->assertSame(1, Question::query()->count());
    }

    public function test_publishing_flashcards_creates_a_document_with_card_content(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'flashcards',
            'title' => 'Thẻ ghi nhớ ôn tập',
            'status' => 'draft',
            'payload' => ['cards' => [
                ['front' => 'Bất đẳng thức Cauchy là gì?', 'back' => 'Bất đẳng thức cho tổng bình phương không nhỏ hơn tích.'],
            ]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('publish', $artifact->id);

        $document = Document::query()->firstOrFail();
        $content = Storage::disk('public')->get($document->file_path);

        $this->assertStringContainsString('**Mặt trước:** Bất đẳng thức Cauchy là gì?', $content);
        $this->assertStringContainsString('**Mặt sau:** Bất đẳng thức cho tổng bình phương', $content);
        $this->assertSame('published', $artifact->fresh()->status);
    }

    public function test_rate_limit_is_recorded_on_the_artifact_instead_of_stopping_the_teacher(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'rate limit']], 429, ['Retry-After' => '45']),
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Questions->value)
            ->set('instruction', 'Soạn câu hỏi số học')
            ->call('generate')
            ->assertSet('activeType', ArtifactType::Questions->value)
            ->assertSet('error', null);

        $artifact = NotebookArtifact::query()->where('notebook_id', $this->notebook->id)->firstOrFail();

        $this->assertSame('failed', $artifact->status);
        $this->assertStringContainsString('giới hạn lượt gọi', (string) $artifact->failedReason());
    }

    public function test_generated_artifact_keeps_its_prompt_and_can_be_regenerated(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Bản tóm tắt thứ nhất.']]],
                    'usage' => ['total_tokens' => 12],
                ], 200)
                ->push([
                    'model' => 'openrouter/free',
                    'choices' => [['message' => ['content' => 'Bản tóm tắt đã tạo lại.']]],
                    'usage' => ['total_tokens' => 13],
                ], 200),
        ]);

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->set('instruction', 'Tóm tắt chủ đề quang học')
            ->call('generate')
            ->assertHasNoErrors();
        $artifact = NotebookArtifact::query()->firstOrFail();

        $this->assertSame('Tóm tắt chủ đề quang học', $artifact->payload['_generation']['instruction']);

        $component->call('regenerate', $artifact->id)->assertSet('error', null);

        $this->assertSame('Bản tóm tắt đã tạo lại.', $artifact->fresh()->text_content);
        $this->assertSame('draft', $artifact->fresh()->status);
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
            ->call('selectType', ArtifactType::Questions->value)
            ->assertSet('activeType', null)
            ->assertSet('view', 'browse')
            ->assertSet('error', 'Loại nội dung này chưa được bật cho môn của bạn.');
    }

    public function test_browse_screen_lists_a_card_per_format(): void
    {
        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSet('view', 'browse')
            ->assertSee('Tạo nội dung mới')
            ->assertSee('Nội dung gần đây')
            ->assertSee('Tạo câu hỏi trắc nghiệm/tự luận/điền khuyết từ nguồn.')
            ->assertSee('wire:click="selectType(\'exam\')"', escape: false)
            ->assertDontSee('Tuỳ chỉnh');
    }

    public function test_selecting_a_format_opens_the_customize_screen(): void
    {
        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Questions->value)
            ->assertSet('view', 'type')
            ->assertSet('activeType', ArtifactType::Questions->value)
            ->assertSee('Tuỳ chỉnh')
            ->assertSee('Gợi ý cho AI')
            ->assertSee('Số câu')
            ->assertSee('Tạo câu hỏi')
            ->assertDontSee('Nội dung gần đây');
    }

    public function test_type_screen_only_lists_artifacts_of_that_type(): void
    {
        $this->createArtifact(ArtifactType::Questions, 'Bộ câu hỏi số học');
        $this->createArtifact(ArtifactType::Document, 'Tóm tắt quang học');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->assertSee('Bộ câu hỏi số học')
            ->assertSee('Tóm tắt quang học')
            ->call('selectType', ArtifactType::Document->value)
            ->assertSee('Tóm tắt quang học')
            ->assertDontSee('Bộ câu hỏi số học')
            ->assertDontSee('Chưa có', escape: false)
            ->call('selectType', ArtifactType::Questions->value)
            ->assertSee('Bộ câu hỏi số học')
            ->assertDontSee('Tóm tắt quang học');
    }

    public function test_back_to_browse_returns_to_the_format_list(): void
    {
        $this->createArtifact(ArtifactType::Document, 'Tóm tắt quang học');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->set('instruction', 'Tóm tắt chủ đề quang học')
            ->call('backToBrowse')
            ->assertSet('view', 'browse')
            ->assertSet('activeType', null)
            ->assertSet('instruction', '')
            ->assertSee('Nội dung gần đây')
            ->assertSee('Tóm tắt quang học');
    }

    public function test_format_cards_show_the_number_of_existing_artifacts(): void
    {
        $this->createArtifact(ArtifactType::Questions, 'Câu hỏi 1');
        $this->createArtifact(ArtifactType::Questions, 'Câu hỏi 2');

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id]);

        $this->assertSame(2, $component->viewData('typeCounts')['questions']);
        $this->assertSame(0, $component->viewData('typeCounts')['document']);
        $this->assertSame(2, $component->viewData('typeCounts')['all']);
    }

    public function test_selecting_a_disabled_format_is_rejected(): void
    {
        $this->subject->features()->where('feature', SubjectFeature::QuestionBank->value)->update(['is_enabled' => false]);
        $this->subject->forgetFeatureCache();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Questions->value)
            ->assertSet('view', 'browse')
            ->assertSee('chưa được bật cho môn của bạn');
    }

    public function test_generating_without_a_format_is_rejected(): void
    {
        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->set('instruction', 'Soạn nội dung bất kỳ')
            ->call('generate')
            ->assertSet('error', 'Hãy chọn một định dạng nội dung trước khi tạo.');

        $this->assertDatabaseCount('notebook_artifacts', 0);
    }

    public function test_generate_opens_the_created_format_screen(): void
    {
        $this->fakeText('Bản tóm tắt đã tạo.');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->set('instruction', 'Tóm tắt chủ đề quang học')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSet('view', 'type')
            ->assertSet('activeType', ArtifactType::Document->value)
            ->assertSet('instruction', '');
    }

    public function test_draft_card_exposes_preview_regenerate_publish_and_delete_actions(): void
    {
        $artifact = $this->createArtifact(ArtifactType::Document, 'Tóm tắt quang học');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->assertSee('wire:click="openPreview('.$artifact->id.')"', escape: false)
            ->assertSee('wire:click="regenerate('.$artifact->id.')"', escape: false)
            ->assertSee('wire:click="publish('.$artifact->id.')"', escape: false)
            ->assertSee('wire:click="delete('.$artifact->id.')"', escape: false);
    }

    public function test_published_card_only_offers_preview_and_download(): void
    {
        $artifact = $this->createArtifact(ArtifactType::Document, 'Tóm tắt quang học');
        $artifact->update(['status' => 'published']);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->assertSee('DOCX', escape: false)
            ->assertDontSee('wire:click="delete('.$artifact->id.')"', escape: false)
            ->assertDontSee('wire:click="publish('.$artifact->id.')"', escape: false);
    }

    public function test_deleting_an_artifact_refreshes_the_current_screen(): void
    {
        $artifact = $this->createArtifact(ArtifactType::Document, 'Tóm tắt quang học');

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('selectType', ArtifactType::Document->value)
            ->call('delete', $artifact->id)
            ->assertDontSee('Tóm tắt quang học')
            ->assertSee('Chưa có');
    }

    private function createArtifact(ArtifactType $type, string $title): NotebookArtifact
    {
        return NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => $type->value,
            'title' => $title,
            'text_content' => 'Nội dung',
            'status' => 'draft',
        ]);
    }

    public function test_other_teacher_cannot_use_studio(): void
    {
        $other = User::factory()->teacher($this->subject)->create();
        $this->actingAs($other);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->assertForbidden();
    }
}
