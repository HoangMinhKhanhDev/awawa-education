<?php

namespace Tests\Feature\Notebook;

use App\Models\Notebook;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\PromptComposer;
use App\Services\Notebook\SourceIngestor;
use App\Services\Notebook\VietnameseTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetrievalTest extends TestCase
{
    use RefreshDatabase;

    private Notebook $notebook;

    protected function setUp(): void
    {
        parent::setUp();

        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->notebook = Notebook::factory()->create([
            'subject_id' => $subject->id,
            'owner_id' => $teacher->id,
        ]);

        $this->actingAs($teacher);
    }

    public function test_vietnamese_terms_match_regardless_of_diacritics(): void
    {
        $this->assertSame(['hoc', 'sinh'], VietnameseTerms::tokenize('Học sinh'));
        $this->assertSame(['duong'], VietnameseTerms::tokenize('đường'));
        $this->assertSame(['hoa'], VietnameseTerms::tokenize('hoá'));

        $this->assertSame(
            VietnameseTerms::frequencies('Photon truyền trong môi trường chân không'),
            VietnameseTerms::frequencies('photon truyen trong moi truong chan khong'),
        );
    }

    public function test_ingesting_a_source_builds_the_term_index(): void
    {
        app(SourceIngestor::class)->fromText($this->notebook, 'Vật lí', 'Photon truyền trong môi trường chân không.');

        $this->assertDatabaseHas('notebook_chunk_terms', ['term' => 'photon']);
        $this->assertDatabaseHas('notebook_chunk_terms', ['term' => 'vat', 'in_title' => true]);
        $this->assertGreaterThan(0, DB::table('notebook_chunks')->where('term_count', '>', 0)->count());
    }

    public function test_retrieval_finds_chunks_without_loading_everything(): void
    {
        config()->set('awawa.notebook.max_context_chunks', 1);

        app(SourceIngestor::class)->fromText($this->notebook, 'Lịch sử', 'Chiến thắng Bạch Đằng là dấu mốc lịch sử.');
        app(SourceIngestor::class)->fromText($this->notebook, 'Quang học', 'Photon truyền trong môi trường chân không.');

        DB::enableQueryLog();

        $context = app(PromptComposer::class)->compose($this->notebook, 'Photon truyền thế nào?');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $context['citations']);
        $this->assertSame('Quang học', $context['citations'][1]['source_title']);

        // Không có query nào đọc cột content của toàn bộ chunk.
        foreach ($queries as $query) {
            $sql = (string) ($query['query'] ?? '');

            if (str_contains($sql, 'notebook_chunks') && str_contains($sql, 'content')) {
                $this->assertStringContainsString('where', strtolower($sql));
                $this->assertDoesNotMatchRegularExpression('/select \* /i', $sql);
            }
        }
    }

    public function test_chunks_created_before_the_index_are_indexed_on_demand(): void
    {
        app(SourceIngestor::class)->fromText($this->notebook, 'Toán', 'Bất đẳng thức Cauchy rất quan trọng.');

        DB::table('notebook_chunk_terms')->truncate();

        $context = app(PromptComposer::class)->compose($this->notebook, 'Cauchy là gì?');

        $this->assertCount(1, $context['citations']);
        $this->assertSame('Toán', $context['citations'][1]['source_title']);
        $this->assertGreaterThan(0, DB::table('notebook_chunk_terms')->count());
    }

    public function test_reingesting_a_source_does_not_duplicate_index_rows(): void
    {
        $ingestor = app(SourceIngestor::class);
        $ingestor->fromText($this->notebook, 'Lí', 'Nội dung ban đầu.');
        $ingestor->fromText($this->notebook, 'Lí', 'Nội dung ban đầu.');

        $duplicates = DB::table('notebook_chunk_terms')
            ->selectRaw('chunk_id, term, in_title, COUNT(*) as total')
            ->groupBy('chunk_id', 'term', 'in_title')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicates);
    }

    public function test_rare_terms_outrank_common_terms(): void
    {
        $ingestor = app(SourceIngestor::class);

        // Từ "quang" xuất hiện ở cả hai nguồn, "lượng tử" chỉ ở một.
        $ingestor->fromText($this->notebook, 'Quang A', 'Quang học nghiên cứu ánh sáng và quang phổ.');
        $ingestor->fromText($this->notebook, 'Quang B', 'Quang hình và quang học ứng dụng. Lượng tử ánh sáng.');

        config()->set('awawa.notebook.max_context_chunks', 1);

        $context = app(PromptComposer::class)->compose($this->notebook, 'Lượng tử là gì?');

        $this->assertCount(1, $context['citations']);
        $this->assertSame('Quang B', $context['citations'][1]['source_title']);
    }
}
