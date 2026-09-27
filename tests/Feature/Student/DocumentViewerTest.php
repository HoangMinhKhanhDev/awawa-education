<?php

namespace Tests\Feature\Student;

use App\Enums\Role;
use App\Livewire\Documents\Show;
use App\Models\Document;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentViewerTest extends TestCase
{
    use RefreshDatabase;

    private const MARKDOWN = '# Bài học: Phân phối Poisson

## Khái niệm

Công thức của **Nhà toán học Pháp S. D. Poisson** (1837) mô tả số sự kiện hiếm.

- X ~ Poisson(λ)
- P(X = k) = λᵏ · e^(−λ) / k!

> Sự kiện xảy ra độc lập với nhau.
';

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::factory()->create();
    }

    private function markdownDocument(array $attributes = []): Document
    {
        $path = 'notebook/ai/vi-du.md';

        Storage::disk('public')->put($path, self::MARKDOWN);

        return Document::factory()->create([
            'subject_id' => $this->subject->id,
            'file_path' => $path,
            'original_name' => 'vi-du.md',
            'mime' => 'text/markdown',
            'size' => strlen(self::MARKDOWN),
            'is_public' => true,
            ...$attributes,
        ]);
    }

    private function pdfDocument(): Document
    {
        $path = UploadedFile::fake()->create('tai-lieu.pdf', 40)->store('documents', 'public');

        return Document::factory()->create([
            'subject_id' => $this->subject->id,
            'file_path' => $path,
            'original_name' => 'tai-lieu.pdf',
            'mime' => 'application/pdf',
            'is_public' => true,
        ]);
    }

    private function student(?Subject $subject = null): User
    {
        $subject ??= $this->subject;

        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $student;
    }

    public function test_markdown_document_is_recognised_as_viewable(): void
    {
        $this->assertTrue($this->markdownDocument()->isViewable());
        $this->assertFalse($this->pdfDocument()->isViewable());
    }

    public function test_viewer_url_points_at_the_in_app_page_for_markdown_only(): void
    {
        $markdown = $this->markdownDocument();
        $pdf = $this->pdfDocument();

        $this->assertSame(route('documents.show', $markdown), $markdown->viewerUrl());
        $this->assertSame($pdf->url(), $pdf->viewerUrl(), 'PDF vẫn phải mở file thật');
    }

    public function test_markdown_is_rendered_as_html_instead_of_raw_source(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $document = $this->markdownDocument();

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertOk()
            ->assertSeeHtml('<h1>Bài học: Phân phối Poisson</h1>')
            ->assertSeeHtml('<h2>Khái niệm</h2>')
            ->assertSeeHtml('<li>')
            ->assertSeeHtml('<blockquote>')
            // Mã nguồn thô không được lọt ra giao diện.
            ->assertDontSee('# Bài học', escape: false)
            ->assertDontSee('**', escape: false);
    }

    public function test_vietnamese_text_is_shown_intact(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $document = $this->markdownDocument();

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertSeeHtml('Nhà toán học Pháp S. D. Poisson')
            ->assertSeeHtml('Sự kiện xảy ra độc lập với nhau');

        // File lưu đúng UTF-8, không bị hỏng khi đọc lại.
        $this->assertSame(self::MARKDOWN, $document->readContent());
    }

    public function test_raw_html_in_the_document_is_stripped(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();

        $document = $this->markdownDocument([
            'file_path' => 'notebook/ai/cong.html',
            'original_name' => 'cong.html',
            'mime' => 'text/plain',
            'size' => 0,
        ]);

        Storage::disk('public')->put(
            $document->file_path,
            "# An toàn\n\n<script>alert('xss')</script>\n\nVăn bản an toàn.",
        );

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertSeeHtml('Văn bản an toàn')
            ->assertDontSee('<script>', escape: false)
            ->assertDontSee("alert('xss')", escape: false);
    }

    public function test_student_can_read_a_public_document_of_their_subject(): void
    {
        $student = $this->student();
        $document = $this->markdownDocument();

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertOk()
            ->assertSeeHtml('Phân phối Poisson');
    }

    public function test_student_cannot_read_a_private_document(): void
    {
        $student = $this->student();
        $document = $this->markdownDocument(['is_public' => false]);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])->assertForbidden();
    }

    public function test_student_cannot_read_a_document_of_another_subject(): void
    {
        $student = $this->student();

        $other = Subject::factory()->create();
        $document = Document::factory()->create([
            'subject_id' => $other->id,
            'is_public' => true,
        ]);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])->assertForbidden();
    }

    public function test_a_self_registered_student_without_a_subject_is_denied(): void
    {
        $document = $this->markdownDocument();

        $this->actingAs(User::factory()->create(['role' => Role::Student->value, 'subject_id' => null]));

        Livewire::test(Show::class, ['document' => $document])->assertForbidden();
    }

    public function test_binary_document_offers_the_original_file_instead_of_markdown(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $document = $this->pdfDocument();

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertOk()
            ->assertSee('application/pdf')
            ->assertSee('Mở tệp gốc')
            ->assertSeeHtml($document->url());
    }

    public function test_a_missing_file_does_not_crash_the_page(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $document = $this->markdownDocument();

        Storage::disk('public')->delete($document->file_path);

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertOk()
            ->assertSee('Không đọc được nội dung tài liệu');
    }

    public function test_an_oversized_document_is_not_loaded_into_the_page(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();

        $document = $this->markdownDocument([
            'size' => Document::MAX_VIEWABLE_BYTES + 1,
        ]);

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Show::class, ['document' => $document])
            ->assertOk()
            ->assertSee('quá lớn để hiển thị')
            ->assertDontSeeHtml('<h1>');
    }
}
