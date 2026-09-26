<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Sources;
use App\Models\AiProvider;
use App\Models\Exam;
use App\Models\Notebook;
use App\Models\NotebookSetting;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
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
            ->call('openAddForm', '')
            ->set('webTopic', 'bất đẳng thức Cauchy')
            ->call('searchWeb')
            ->assertHasNoErrors()
            ->assertSet('addOpen', false);

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
            ->call('openAddForm', '')
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

    public function test_add_selected_web_sources_stores_full_tavily_extract_content(): void
    {
        $pageContent = str_repeat('Toàn văn trang giải thích định lý Cauchy và các bước chứng minh. ', 30);
        Http::fake([
            'api.tavily.com/search' => Http::response([
                'results' => [[
                    'title' => 'Toàn văn Cauchy',
                    'url' => 'https://example.com/cauchy',
                    'content' => 'Đoạn trích ngắn từ kết quả tìm kiếm.',
                    'score' => 0.98,
                ]],
            ], 200),
            'api.tavily.com/extract' => Http::response([
                'results' => [['url' => 'https://example.com/cauchy', 'raw_content' => $pageContent]],
            ], 200),
            'openrouter.ai/*' => Http::response([
                'model' => 'openrouter/free',
                'choices' => [['message' => ['content' => '[{"index":1,"keep":true,"reason":"Tài liệu phù hợp"}]']]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('webTopic', 'bất đẳng thức Cauchy')
            ->call('searchWeb')
            ->set('webSelected', [0])
            ->call('addWebSources');

        $source = $this->notebook->sources()->firstOrFail();

        $this->assertSame('ready', $source->status);
        $this->assertSame(trim($pageContent), $source->raw_content);
        $this->assertGreaterThan(1000, $source->char_count);
        $this->assertStringContainsString('các bước chứng minh', $source->chunks()->first()->content);
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/extract')
            && $request['urls'] === ['https://example.com/cauchy']);
    }

    public function test_teacher_can_add_questions_and_exams_as_sources(): void
    {
        $question = Question::factory()->create(['subject_id' => $this->subject->id, 'content' => 'Nội dung câu hỏi nguồn']);
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'title' => 'Đề ôn tập nguồn']);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'internal')
            ->set('internalType', 'question')
            ->set('questionId', $question->id)
            ->call('addInternalSource')
            ->call('openAddForm', 'internal')
            ->set('internalType', 'exam')
            ->set('examId', $exam->id)
            ->call('addInternalSource')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notebook_sources', [
            'notebook_id' => $this->notebook->id,
            'type' => 'question',
            'ref_id' => $question->id,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('notebook_sources', [
            'notebook_id' => $this->notebook->id,
            'type' => 'exam',
            'ref_id' => $exam->id,
            'status' => 'ready',
        ]);
    }

    public function test_teacher_can_retry_a_failed_web_source(): void
    {
        $source = app(SourceIngestor::class)->fromText(
            $this->notebook,
            'Trang cần tải lại',
            '',
            'web',
            ['url' => 'https://example.com/retry'],
        );
        Http::fake([
            'api.tavily.com/extract' => Http::response([
                'results' => [['url' => 'https://example.com/retry', 'raw_content' => 'Nội dung trang đã trích xuất lại.']],
            ], 200),
        ]);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('retrySource', $source->id)
            ->assertSet('error', null);

        $this->assertSame('ready', $source->fresh()->status);
        $this->assertStringContainsString('trích xuất lại', $source->fresh()->chunks()->first()->content);
    }

    public function test_citation_opens_and_highlights_its_source_chunk(): void
    {
        $source = app(SourceIngestor::class)->fromText($this->notebook, 'Nguồn trích dẫn', 'Nội dung để mở đúng đoạn.');
        $chunk = $source->chunks()->firstOrFail();

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('viewCitation', $source->id, $chunk->id)
            ->assertSet('viewingSourceId', $source->id)
            ->assertSet('highlightChunkId', $chunk->id);
    }

    public function test_file_upload_obeys_the_admin_megabyte_limit(): void
    {
        NotebookSetting::set('notebook_max_file_mb', '1');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('files', [UploadedFile::fake()->create('large.txt', 1025, 'text/plain')])
            ->call('addFiles')
            ->assertHasErrors(['files.0' => 'max']);

        $this->assertSame(0, $this->notebook->sources()->count());
    }

    public function test_search_still_returns_results_when_ai_is_rate_limited(): void
    {
        Http::fake([
            'api.tavily.com/search' => Http::response([
                'results' => [[
                    'title' => 'Định lý Cauchy',
                    'url' => 'https://example.com/cauchy',
                    'content' => 'Đoạn trích về định lý Cauchy.',
                    'score' => 0.9,
                ]],
            ], 200),
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'free tier limit']], 429, ['Retry-After' => '60']),
        ]);

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', '')
            ->set('webTopic', 'định lý Cauchy')
            ->call('searchWeb')
            ->assertHasNoErrors();

        $results = $component->get('webResults');

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['keep']);
        $this->assertSame([], $component->get('webSelected'));
    }

    public function test_search_without_tavily_key_shows_error(): void
    {
        NotebookSetting::forget('tavily_api_key');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', '')
            ->set('webTopic', 'chủ đề')
            ->call('searchWeb')
            ->assertSet('error', fn ($value) => is_string($value) && $value !== '');
    }
}
