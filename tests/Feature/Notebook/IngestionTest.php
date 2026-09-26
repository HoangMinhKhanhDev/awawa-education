<?php

namespace Tests\Feature\Notebook;

use App\Models\Document;
use App\Models\Notebook;
use App\Models\NotebookSource;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notebook\SourceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IngestionTest extends TestCase
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

        $this->ingestor = app(SourceIngestor::class);
    }

    private function longText(int $length = 2500): string
    {
        return trim(str_repeat('Bất đẳng thức Cauchy là một bất đẳng thức quan trọng. ', (int) ceil($length / 55)));
    }

    public function test_text_source_is_chunked_with_offsets(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'Chuyên đề', $this->longText());

        $this->assertSame('ready', $source->status);
        $this->assertGreaterThan(1, $source->chunks()->count());
        $this->assertGreaterThan(0, $source->char_count);

        $first = $source->chunks()->orderBy('position')->first();
        $second = $source->chunks()->orderBy('position')->skip(1)->first();

        $this->assertSame(0, $first->char_start);
        $this->assertSame(850, $second->char_start);
        $this->assertSame($first->char_end - 150, $second->char_start);
    }

    public function test_text_source_is_failed_when_empty(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'Rỗng', '   ');

        $this->assertSame('failed', $source->status);
        $this->assertNotNull($source->error);
    }

    public function test_question_source_composes_text(): void
    {
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'content' => 'Giá trị của 2 + 2 là?',
        ]);
        $question->options()->create(['content' => '4', 'is_correct' => true, 'order' => 0]);
        $question->options()->create(['content' => '5', 'is_correct' => false, 'order' => 1]);

        $source = $this->ingestor->fromQuestion($this->notebook, $question);

        $this->assertSame('ready', $source->status);
        $this->assertSame('question', $source->type);
        $this->assertStringContainsString('Giá trị của 2 + 2', $source->chunks()->first()->content);
    }

    public function test_document_source_extracts_txt_file(): void
    {
        Storage::disk('public')->put('docs/tailieu.txt', 'Nội dung tài liệu về đạo hàm.');

        $document = Document::factory()->create([
            'subject_id' => $this->subject->id,
            'file_path' => 'docs/tailieu.txt',
            'original_name' => 'tailieu.txt',
            'mime' => 'text/plain',
        ]);

        $source = $this->ingestor->fromDocument($this->notebook, $document);

        $this->assertSame('ready', $source->status);
        $this->assertStringContainsString('đạo hàm', $source->chunks()->first()->content);
    }

    public function test_unsupported_file_type_is_marked_failed(): void
    {
        $file = UploadedFile::fake()->create('archive.bin', 1, 'application/octet-stream');

        $source = $this->ingestor->fromUpload($this->notebook, $file);

        $this->assertSame('failed', $source->status);
        $this->assertNotNull($source->error);
    }

    public function test_remove_deletes_source_and_chunks(): void
    {
        $source = $this->ingestor->fromText($this->notebook, 'X', $this->longText());

        $this->assertDatabaseHas('notebook_chunks', ['source_id' => $source->id]);

        $this->ingestor->remove($source);

        $this->assertDatabaseMissing('notebook_sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('notebook_chunks', ['source_id' => $source->id]);
    }

    public function test_notebook_ownership(): void
    {
        $other = User::factory()->teacher($this->subject)->create();

        $this->assertTrue($this->notebook->isOwnedBy($this->teacher));
        $this->assertFalse($this->notebook->isOwnedBy($other));
    }

    public function test_enabled_source_ids_excludes_disabled_and_failed(): void
    {
        $ready = $this->ingestor->fromText($this->notebook, 'A', $this->longText());
        $disabled = $this->ingestor->fromText($this->notebook, 'B', 'Nội dung ngắn.');
        $disabled->forceFill(['is_enabled' => false])->save();
        $failed = $this->ingestor->fromText($this->notebook, 'C', '  ');

        $ids = $this->notebook->enabledSourceIds();

        $this->assertContains($ready->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
        $this->assertNotContains($failed->id, $ids);
    }

    public function test_sources_belong_to_notebook(): void
    {
        $this->ingestor->fromText($this->notebook, 'A', 'nội dung');

        $this->assertSame(1, NotebookSource::query()->where('notebook_id', $this->notebook->id)->count());
    }
}
