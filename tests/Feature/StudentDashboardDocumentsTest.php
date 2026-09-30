<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Document;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Trang chủ học sinh có ô tìm tài liệu công khai, dùng public property
 * `$docSearch`. Nếu property không có mặt trong view thì trang trắng.
 */
class StudentDashboardDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_renders_the_document_search(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Document::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $student->id,
            'title' => 'Đề cương ôn thi',
            'is_public' => true,
        ]);

        app(SubjectContext::class)->set($subject->id);

        Livewire::actingAs($student)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Tài liệu công khai')
            ->assertSee('Đề cương ôn thi');
    }

    public function test_document_search_filters_the_list(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach (['Bài giảng chuyên đề', 'Đề cương ôn thi'] as $title) {
            Document::factory()->create([
                'subject_id' => $subject->id,
                'created_by' => $student->id,
                'title' => $title,
                'is_public' => true,
            ]);
        }

        app(SubjectContext::class)->set($subject->id);

        Livewire::actingAs($student)
            ->test(Dashboard::class)
            ->set('docSearch', 'chuyên')
            ->assertOk()
            ->assertSee('Bài giảng chuyên đề')
            ->assertDontSee('Đề cương ôn thi');
    }

    public function test_empty_result_message_uses_the_search_term(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(SubjectContext::class)->set($subject->id);

        Livewire::actingAs($student)
            ->test(Dashboard::class)
            ->set('docSearch', 'khong-ton-tai')
            ->assertOk()
            ->assertSee('Không tìm thấy tài liệu nào.');
    }
}
