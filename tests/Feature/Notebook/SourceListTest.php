<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Sources;
use App\Models\Notebook;
use App\Models\NotebookSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\HighlightPicker;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SourceListTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private Notebook $notebook;

    private SourceIngestor $ingestor;

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
        $this->ingestor = app(SourceIngestor::class);
        NotebookSetting::set('tavily_api_key', 'tvly-test', true);
    }

    private function fakeExtract(array $pages): void
    {
        $results = [];

        foreach ($pages as $url => $content) {
            $results[] = ['url' => $url, 'raw_content' => $content];
        }

        Http::fake([
            'api.tavily.com/extract' => Http::response(['results' => $results], 200),
        ]);
    }

    public function test_pasted_urls_become_one_source_each(): void
    {
        $this->fakeExtract([
            'https://example.com/cong-thuc' => 'Công thức Cauchy: a²+b² ≥ c².',
            'https://example.com/luyen-tap' => 'Bài tập về định lý Cauchy.',
        ]);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'url')
            ->set('urlText', "https://example.com/cong-thuc\nhttps://example.com/luyen-tap")
            ->call('addUrls');

        $this->assertSame(2, $this->notebook->sources()->count());
        $this->assertSame(
            ['https://example.com/cong-thuc', 'https://example.com/luyen-tap'],
            $this->notebook->sources()->pluck('url')->all(),
        );
        $this->assertStringContainsString('a²+b²', $this->notebook->sources()->first()->chunks()->first()->content);
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/extract')
            && count($request['urls']) === 2);
    }

    public function test_pasted_urls_skip_invalid_and_duplicate_links(): void
    {
        $this->fakeExtract([
            'https://example.com/a' => 'Nội dung trang A đủ dài để thành nguồn.',
            'https://example.com/b' => 'Nội dung trang B đủ dài để thành nguồn.',
        ]);

        $this->ingestor->fromText($this->notebook, 'Trang A', 'Nội dung trang A.', 'web', ['url' => 'https://example.com/a']);

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'url')
            ->set('urlText', "không-phải-link\nhttps://example.com/a\nhttps://example.com/a/\nhttps://example.com/b")
            ->call('addUrls');

        $this->assertSame(2, $this->notebook->sources()->count());
        $this->assertCount(2, $component->get('urlSkipped'));
        $this->assertSame([], $component->get('urlFailed'));
    }

    public function test_pasted_urls_respect_the_source_limit(): void
    {
        NotebookSetting::set('notebook_max_sources', '1');
        $this->fakeExtract([
            'https://example.com/a' => 'Nội dung trang A.',
            'https://example.com/b' => 'Nội dung trang B.',
        ]);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'url')
            ->set('urlText', "https://example.com/a\nhttps://example.com/b")
            ->call('addUrls');

        $this->assertSame(1, $this->notebook->sources()->count());
    }

    public function test_multiple_files_each_become_a_source(): void
    {
        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'file')
            ->set('files', [
                UploadedFile::fake()->createWithContent('bai-giang.txt', 'Bài giảng về định lý Cauchy và các ví dụ.'),
                UploadedFile::fake()->createWithContent('de-tap.txt', 'Đề tập ôn định lý Cauchy.'),
            ])
            ->call('addFiles')
            ->assertHasNoErrors();

        $this->assertSame(2, $this->notebook->sources()->count());
        $this->assertSame(
            ['bai-giang.txt', 'de-tap.txt'],
            $this->notebook->sources()->orderBy('order')->pluck('original_name')->all(),
        );
    }

    public function test_more_files_than_the_batch_limit_is_rejected(): void
    {
        $files = [];

        for ($index = 0; $index <= Sources::MAX_FILES_PER_BATCH; $index++) {
            $files[] = UploadedFile::fake()->createWithContent("file-{$index}.txt", 'Nội dung tệp số '.$index.' đủ dài.');
        }

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'file')
            ->set('files', $files)
            ->call('addFiles')
            ->assertHasErrors('files');

        $this->assertSame(0, $this->notebook->sources()->count());
    }

    public function test_a_broken_file_does_not_block_the_others(): void
    {
        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('openAddForm', 'file')
            ->set('files', [
                UploadedFile::fake()->createWithContent('broken.pdf', 'đây không phải là tệp PDF'),
                UploadedFile::fake()->createWithContent('ok.txt', 'Nội dung hợp lệ của tệp thứ hai.'),
            ])
            ->call('addFiles')
            ->assertSet('error', fn ($value) => is_string($value) && str_contains($value, 'broken.pdf'));

        $this->assertSame(1, $this->notebook->sources()->where('status', 'ready')->count());
        $this->assertSame(1, $this->notebook->sources()->where('status', 'failed')->count());
        $this->assertSame('ok.txt', $this->notebook->sources()->where('status', 'ready')->first()->original_name);
    }

    public function test_list_can_be_searched_and_filtered_by_type(): void
    {
        $this->ingestor->fromText($this->notebook, 'Bài giảng Cauchy', 'Nội dung bài giảng về định lý Cauchy.');
        $this->ingestor->fromText($this->notebook, 'Tóm tắt Lịch sử', 'Nội dung tóm tắt lịch sử Việt Nam.', 'web', [
            'url' => 'https://example.com/lich-su',
        ]);

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id]);

        $this->assertCount(2, $component->viewData('sources'));

        $component->set('sourceQuery', 'Cauchy');
        $this->assertSame(['Bài giảng Cauchy'], $component->viewData('sources')->pluck('title')->all());

        $component->set('sourceQuery', '')->set('sourceTypeFilter', 'web');
        $this->assertSame(['Tóm tắt Lịch sử'], $component->viewData('sources')->pluck('title')->all());
    }

    public function test_bulk_toggle_only_affects_visible_sources(): void
    {
        $visible = $this->ingestor->fromText($this->notebook, 'Bài giảng Cauchy', 'Nội dung bài giảng Cauchy.');
        $hidden = $this->ingestor->fromText($this->notebook, 'Tóm tắt Lịch sử', 'Nội dung lịch sử.', 'web', [
            'url' => 'https://example.com/lich-su',
        ]);
        $hidden->forceFill(['is_enabled' => false])->save();

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('sourceTypeFilter', 'text')
            ->call('selectAllVisible', false);

        $this->assertFalse($visible->fresh()->is_enabled);
        $this->assertFalse($hidden->fresh()->is_enabled);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->set('sourceTypeFilter', 'text')
            ->call('selectAllVisible', true);

        $this->assertTrue($visible->fresh()->is_enabled);
        $this->assertFalse($hidden->fresh()->is_enabled);
    }

    public function test_source_row_shows_status_type_and_size(): void
    {
        $this->ingestor->fromText($this->notebook, 'Bài gi��ng Cauchy', 'Nội dung bài giảng Cauchy.');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->assertSee('Sẵn sàng')
            ->assertSee('Văn bản')
            ->assertSee('1 đoạn');
    }

    public function test_ingested_source_extracts_key_passages_distinct_from_full_text(): void
    {
        $text = "Công nghệ 12 là môn học giới thiệu kỹ thuật điện tử.\n"
            ."Định nghĩa: linh kiện điện tử là linh kiện dùng để dẫn điện hoặc kiểm soát dòng điện.\n"
            ."Một số linh kiện có định trở thuận như điện trở, tụ điện.\n"
            .str_repeat('Nội dung lấp đầy để tạo thêm các đoạn cho tài liệu dài hơn. ', 60)
            .'Kết luận: việc phân tích linh kiện giúp thiết kế mạch điện tử chính xác hơn.';

        $source = $this->ingestor->fromText($this->notebook, 'Giáo trình công nghệ 12', $text);

        $passages = $source->chunks()->get()->map(fn ($chunk): array => [
            'id' => $chunk->id,
            'position' => $chunk->position,
            'content' => (string) $chunk->content,
        ])->all();

        $selected = app(HighlightPicker::class)->passages($passages);

        $this->assertNotEmpty($selected);
        $this->assertLessThanOrEqual(8, count($selected));
        $this->assertStringContainsString('Định nghĩa', $selected[0]['text']);

        $highlightedChars = mb_strlen(implode(' ', array_column($selected, 'text')));
        $this->assertLessThan($source->char_count, $highlightedChars, 'Câu trích phải ngắn hơn nhiều so với toàn văn');

        $component = Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('view', $source->id)
            ->assertSet('viewerTab', 'highlights');

        $this->assertCount(count($selected), $component->viewData('viewingPassages'));
    }

    public function test_short_source_reports_that_it_has_no_key_passage(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'Ghi chú ngắn', 'Ngắn quá.');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('view', $source->id)
            ->assertSee('quá ngắn nên chưa có câu trích nổi bật');
    }

    public function test_clicking_a_passage_jumps_to_that_chunk_in_full_text(): void
    {
        $text = str_repeat('Nội dung lấp đầy để tạo thành nhiều đoạn khác nhau trong tài liệu. ', 60)
            .'Định nghĩa: đây là câu trích quan trọng nhất của tài liệu.';

        $source = $this->ingestor->fromText($this->notebook, 'Tài liệu dài', $text);
        $passage = app(HighlightPicker::class)->passages(
            $source->chunks()->get()->map(fn ($chunk): array => [
                'id' => $chunk->id,
                'position' => $chunk->position,
                'content' => (string) $chunk->content,
            ])->all(),
        )[0];

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('view', $source->id)
            ->call('jumpToChunk', $passage['chunk_id'])
            ->assertSet('viewerTab', 'full')
            ->assertSet('highlightChunkId', $passage['chunk_id']);
    }

    public function test_ask_about_a_source_scopes_the_chat_to_that_source(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'Bài giảng Cauchy', 'Nội dung bài giảng Cauchy.');

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('askAbout', $source->id)
            ->assertDispatched('notebook-ask-source', sourceId: $source->id, sourceTitle: 'Bài giảng Cauchy');
    }

    public function test_ask_about_a_failed_source_is_not_allowed(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'Trang lỗi', '   ', 'web', ['url' => 'https://example.com/lỗi']);

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])
            ->call('askAbout', $source->id)
            ->assertNotDispatched('notebook-ask-source');
    }

    public function test_other_teacher_cannot_manage_sources(): void
    {
        $this->actingAs(User::factory()->teacher($this->subject)->create());

        Livewire::test(Sources::class, ['notebookId' => $this->notebook->id])->assertForbidden();
    }
}
