<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_public_documents_of_own_subject_only(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create();

        Document::factory()->create(['subject_id' => $subject->id, 'is_public' => true, 'title' => 'Chuyên đề công khai']);
        Document::factory()->create(['subject_id' => $subject->id, 'is_public' => false, 'title' => 'Tài liệu riêng tư']);
        Document::factory()->create(['subject_id' => Subject::factory()->create()->id, 'is_public' => true, 'title' => 'Tài liệu môn khác']);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Chuyên đề công khai')
            ->assertDontSee('Tài liệu riêng tư')
            ->assertDontSee('Tài liệu môn khác');
    }

    public function test_guest_student_sees_public_documents_across_subjects(): void
    {
        $guest = User::factory()->student()->create();
        $otherSubject = Subject::factory()->create();

        Document::factory()->create(['subject_id' => $otherSubject->id, 'is_public' => true, 'title' => 'Tài liệu môn khác công khai']);
        Document::factory()->create(['subject_id' => $otherSubject->id, 'is_public' => false, 'title' => 'Tài liệu riêng tư khác']);

        $this->actingAs($guest)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tài liệu công khai')
            ->assertSee('Tài liệu môn khác công khai')
            ->assertDontSee('Tài liệu riêng tư khác');
    }
}
