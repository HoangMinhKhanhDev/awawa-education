<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\DocumentsIndex;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);
    }

    public function test_teacher_can_upload_document(): void
    {
        $file = UploadedFile::fake()->create('chuyen-de-1.pdf', 200, 'application/pdf');

        Livewire::test(DocumentsIndex::class)
            ->call('openCreate')
            ->set('title', 'Chuyên đề 1')
            ->set('category', 'Chuyên đề')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::query()->firstOrFail();

        $this->assertSame($this->subject->id, $document->subject_id);
        $this->assertSame('chuyen-de-1.pdf', $document->original_name);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_deleting_document_removes_file(): void
    {
        $document = Document::factory()->create(['subject_id' => $this->subject->id]);
        Storage::disk('public')->put($document->file_path, 'nội dung');

        Livewire::test(DocumentsIndex::class)->call('delete', $document->id);

        Storage::disk('public')->assertMissing($document->file_path);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_teacher_can_toggle_visibility(): void
    {
        $document = Document::factory()->create(['subject_id' => $this->subject->id, 'is_public' => true]);

        Livewire::test(DocumentsIndex::class)->call('togglePublic', $document->id);

        $this->assertFalse($document->fresh()->is_public);
    }
}
