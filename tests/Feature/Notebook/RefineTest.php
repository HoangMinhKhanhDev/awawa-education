<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Enums\SubjectFeature;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\NotebookArtifactRefine;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RefineTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function questionsArtifact(): NotebookArtifact
    {
        return NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => ArtifactType::Questions->value,
            'title' => 'Câu hỏi số học',
            'payload' => ['items' => [
                ['type' => 'essay', 'content' => 'Tính 1 + 1.', 'points' => 1],
                ['type' => 'essay', 'content' => 'Tính 2 + 2.', 'points' => 1],
                ['type' => 'essay', 'content' => 'Tính 3 + 3.', 'points' => 1],
            ]],
            'status' => 'draft',
        ]);
    }

    private function documentArtifact(): NotebookArtifact
    {
        return NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => ArtifactType::Document->value,
            'title' => 'Tóm tắt số học',
            'payload' => [],
            'text_content' => "Phép cộng rất dễ.\n\nPhép nhân rất khó.",
            'status' => 'draft',
        ]);
    }

    private function fakeRefine(array $proposal): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode($proposal, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);
    }

    public function test_refine_edits_only_the_requested_questions(): void
    {
        $artifact = $this->questionsArtifact();

        $this->fakeRefine([
            'summary' => 'Đã làm khó câu 2.',
            'edits' => [
                ['index' => 2, 'type' => 'essay', 'content' => 'Chứng minh 2 + 2 = 4.', 'points' => 2],
            ],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->set('refineInstruction', 'Làm khó câu 2 lên')
            ->call('sendRefine')
            ->assertSet('refineError', null);

        $refine = NotebookArtifactRefine::query()->firstOrFail();

        $this->assertSame('pending', $refine->status);
        $this->assertSame('Đã làm khó câu 2.', $refine->summary);
        $this->assertCount(1, $refine->proposal['edits']);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('applyRefine', $refine->id);

        $artifact->refresh();

        $items = $artifact->payload['items'];

        // Câu 2 đổi, câu 1 và 3 giữ nguyên.
        $this->assertSame('Tính 1 + 1.', $items[0]['content']);
        $this->assertSame('Chứng minh 2 + 2 = 4.', $items[1]['content']);
        $this->assertSame(2, $items[1]['points']);
        $this->assertSame('Tính 3 + 3.', $items[2]['content']);

        $this->assertSame('applied', $refine->fresh()->status);
    }

    public function test_refine_skips_nonexistent_indexes(): void
    {
        $artifact = $this->questionsArtifact();

        $this->fakeRefine([
            'summary' => 'Đã sửa.',
            'edits' => [
                ['index' => 99, 'content' => 'Câu không tồn tại.'],
                ['index' => 1, 'content' => 'Tính 1 + 1 + 1.'],
            ],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->set('refineInstruction', 'Sửa câu 99 và 1')
            ->call('sendRefine');

        $refine = NotebookArtifactRefine::query()->firstOrFail();

        $this->assertCount(1, $refine->proposal['edits']);
        $this->assertStringContainsString('99', (string) $refine->note);
    }

    public function test_refine_replaces_a_prose_passage(): void
    {
        $artifact = $this->documentArtifact();

        $this->fakeRefine([
            'summary' => 'Đã sửa đoạn phép nhân.',
            'replacements' => [
                ['find' => 'Phép nhân rất khó.', 'replace' => 'Phép nhân cần luyện nhiều.'],
            ],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->set('refineInstruction', 'Sửa đoạn phép nhân')
            ->call('sendRefine');

        $refine = NotebookArtifactRefine::query()->firstOrFail();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('applyRefine', $refine->id);

        $this->assertStringContainsString('Phép nhân cần luyện nhiều.', $artifact->fresh()->text_content);
        $this->assertStringContainsString('Phép cộng rất dễ.', $artifact->fresh()->text_content);
    }

    public function test_dismissed_proposal_does_not_touch_the_draft(): void
    {
        $artifact = $this->questionsArtifact();

        $this->fakeRefine([
            'summary' => 'Đã sửa.',
            'edits' => [['index' => 1, 'content' => 'Nội dung khác.']],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->set('refineInstruction', 'Sửa câu 1')
            ->call('sendRefine');

        $refine = NotebookArtifactRefine::query()->firstOrFail();

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->call('dismissRefine', $refine->id);

        $this->assertSame('dismissed', $refine->fresh()->status);
        $this->assertSame('Tính 1 + 1.', $artifact->fresh()->payload['items'][0]['content']);
    }

    public function test_published_artifact_cannot_be_refined(): void
    {
        $artifact = $this->questionsArtifact();
        $artifact->update(['status' => 'published']);

        $component = Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->set('refineInstruction', 'Sửa câu 1');

        try {
            $component->call('sendRefine');
            $this->fail('Bản đã xuất bản phải bị chặn sửa.');
        } catch (\Throwable $exception) {
            $this->assertSame(0, NotebookArtifactRefine::query()->count());
        }
    }
}
