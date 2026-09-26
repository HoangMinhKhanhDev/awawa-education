<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Sources;
use App\Models\AiProvider;
use App\Models\Notebook;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WebSourceTest extends TestCase
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

        NotebookSetting::set('tavily_api_key', 'tvly-test', true);

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

    private function fakeWeb(): void
    {
        Http::fake([
            'api.tavily.com/*' => Http::response([
                'results' => [
                    ['title' => 'Cauchy nâng cao', 'url' => 'https://example.com/a', 'content' => 'Nội dung Cauchy.', 'score' => 0.9],
                    ['title' => 'Quảng cáo', 'url' => 'https://spam.example/b', 'content' => 'Nội dung rác.', 'score' => 0.2],
                ],
            ], 200),
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => json_encode([
                    ['index' => 1, 'keep' => true, 'reason' => 'Đúng chủ đề'],
                    ['index' => 2, 'keep' => false, 'reason' => 'Không liên quan'],
                ], JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['total_tokens' => 15],
            ], 200),
        ]);
    }

    public function test_search_returns_results_with_quality_flags(): void
    {
        $this->fakeWeb();

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('addType', 'web')
            ->set('webTopic', 'bất đẳng thức Cauchy')
            ->call('searchWeb')
            ->assertHasNoErrors();

        $results = $component->get('webResults');

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['keep']);
        $this->assertFalse($results[1]['keep']);
        // chỉ nguồn chất lượng được chọn sẵn
        $this->assertSame([0], $component->get('webSelected'));
    }

    public function test_add_selected_web_sources_creates_sources(): void
    {
        $this->fakeWeb();

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('addType', 'web')
            ->set('webTopic', 'bất đẳng thức Cauchy')
            ->call('searchWeb')
            ->set('webSelected', [0])
            ->call('addWebSources');

        $this->assertSame(1, $this->notebook->sources()->count());

        $source = $this->notebook->sources()->first();

        $this->assertSame('web', $source->type);
        $this->assertSame('https://example.com/a', $source->url);
        $this->assertSame('ready', $source->status);
    }

    public function test_search_without_tavily_key_shows_error(): void
    {
        NotebookSetting::forget('tavily_api_key');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('addType', 'web')
            ->set('webTopic', 'chủ đề')
            ->call('searchWeb')
            ->assertSet('error', fn ($value) => is_string($value) && $value !== '');
    }
}
