<?php

namespace Tests\Feature\Notebook;

use App\Enums\ArtifactType;
use App\Livewire\Notebook\Studio;
use App\Models\AiProvider;
use App\Models\KnowledgeMap;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\ArtifactGenerator;
use App\Support\MindMapTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MindMapTest extends TestCase
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

    private function fakeMindMap(array $nodes): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode(['title' => 'Chủ đề', 'nodes' => $nodes], JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 42],
            ], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMindMap(array $params = []): array
    {
        return app(ArtifactGenerator::class)->generate(
            $this->notebook,
            ArtifactType::MindMap,
            array_merge(['instruction' => 'Sơ đồ ôn tập', 'mindmap_branches' => 3], $params),
            $this->subject->id,
            $this->teacher->id,
        )['payload'];
    }

    public function test_dangling_parent_and_duplicate_id_are_repaired(): void
    {
        $this->fakeMindMap([
            ['id' => 'n1', 'label' => 'Gốc', 'parent' => null],
            ['id' => 'n2', 'label' => 'Nhánh', 'parent' => 'khong-ton-tai'],
            ['id' => 'n2', 'label' => 'Trùng id', 'parent' => 'n1'],
            ['id' => 'n3', 'label' => 'Tự trỏ', 'parent' => 'n3'],
        ]);

        $payload = $this->generateMindMap();

        $byId = collect($payload['nodes'])->keyBy('id');

        // Parent lạ và tự tham chiếu đều về tầng gốc, id trùng chỉ giữ một.
        $this->assertNull($byId['n2']['parent']);
        $this->assertNull($byId['n3']['parent']);
        $this->assertCount(3, $payload['nodes']);
    }

    public function test_first_node_becomes_root_when_missing(): void
    {
        $this->fakeMindMap([
            ['id' => 'n1', 'label' => 'Một', 'parent' => 'n2'],
            ['id' => 'n2', 'label' => 'Hai', 'parent' => 'n1'],
        ]);

        $payload = $this->generateMindMap();

        $roots = collect($payload['nodes'])->where('parent', null);

        $this->assertCount(1, $roots);
    }

    public function test_branch_count_is_sent_in_the_prompt(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'openrouter/free',
            'choices' => [['message' => ['content' => json_encode(['title' => 'T', 'nodes' => [['id' => 'n1', 'label' => 'Gốc', 'parent' => null]]], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['total_tokens' => 10],
        ], 200)]);
        Http::preventStrayRequests();

        $this->generateMindMap(['mindmap_branches' => 4]);

        Http::assertSent(fn ($request): bool => str_contains(
            (string) ($request->data()['messages'][1]['content'] ?? ''),
            'đúng 4 nhánh chính',
        ));
    }

    public function test_tree_builder_survives_a_cycle(): void
    {
        $tree = MindMapTree::build([
            ['id' => 'n1', 'label' => 'Một', 'parent' => 'n2'],
            ['id' => 'n2', 'label' => 'Hai', 'parent' => 'n1'],
        ]);

        $this->assertNotEmpty($tree);

        $labels = [];

        $collect = function (array $items) use (&$collect, &$labels): void {
            foreach ($items as $item) {
                $labels[] = $item['node']['label'];
                $collect($item['children']);
            }
        };
        $collect($tree);

        // Mỗi node hiện đúng một lần, không treo, không lặp.
        $this->assertSame(['Một', 'Hai'], $labels);
    }

    public function test_published_scene_centers_parent_over_children(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'mindmap',
            'title' => 'Sơ đồ',
            'status' => 'draft',
            'payload' => ['nodes' => [
                ['id' => 'n1', 'label' => 'Gốc', 'parent' => null],
                ['id' => 'n2', 'label' => 'Nhánh một', 'parent' => 'n1'],
                ['id' => 'n3', 'label' => 'Nhánh hai', 'parent' => 'n1'],
            ]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])->call('publish', $artifact->id);

        $scene = KnowledgeMap::query()->firstOrFail()->latestVersion->scene;
        $rects = collect($scene['elements'])->where('type', 'rectangle')->keyBy('id');

        $this->assertCount(3, $rects);

        // Cha nằm giữa hai con theo chiều dọc.
        $parentCenter = $rects['rect-n1']['y'] + $rects['rect-n1']['height'] / 2;
        $childCenter = ($rects['rect-n2']['y'] + $rects['rect-n2']['height'] / 2
            + $rects['rect-n3']['y'] + $rects['rect-n3']['height'] / 2) / 2;

        $this->assertEqualsWithDelta($childCenter, $parentCenter, 1.0);

        // Màu theo tầng: gốc khác nhánh con.
        $this->assertNotSame($rects['rect-n1']['backgroundColor'], $rects['rect-n2']['backgroundColor']);
    }

    public function test_preview_renders_a_nested_tree(): void
    {
        $artifact = NotebookArtifact::create([
            'notebook_id' => $this->notebook->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'mindmap',
            'title' => 'Sơ đồ',
            'status' => 'draft',
            'payload' => ['nodes' => [
                ['id' => 'n1', 'label' => 'Gốc cây', 'parent' => null],
                ['id' => 'n2', 'label' => 'Lá con', 'parent' => 'n1'],
            ]],
        ]);

        Livewire::test(Studio::class, ['notebookId' => $this->notebook->id])
            ->call('openPreview', $artifact->id)
            ->assertSee('Gốc cây')
            ->assertSee('Lá con')
            ->assertDontSee('(thuộc n1)');
    }
}
