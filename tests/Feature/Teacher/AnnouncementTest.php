<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\AnnouncementsIndex;
use App\Models\Announcement;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);
    }

    public function test_teacher_can_publish_announcement(): void
    {
        Livewire::test(AnnouncementsIndex::class)
            ->call('openCreate')
            ->set('title', 'Lịch kiểm tra')
            ->set('body', 'Kiểm tra chuyên đề 1 vào thứ Sáu.')
            ->set('publishNow', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('announcements', [
            'subject_id' => $this->subject->id,
            'title' => 'Lịch kiểm tra',
        ]);

        $this->assertNotNull(Announcement::query()->first()->published_at);
    }

    public function test_scope_published_excludes_drafts(): void
    {
        Announcement::factory()->create(['subject_id' => $this->subject->id]);
        Announcement::factory()->draft()->create(['subject_id' => $this->subject->id]);

        $this->assertSame(1, Announcement::query()->published()->count());
        $this->assertSame(2, Announcement::query()->count());
    }

    public function test_student_sees_published_but_not_draft(): void
    {
        Announcement::factory()->create(['subject_id' => $this->subject->id, 'title' => 'Đã đăng']);
        Announcement::factory()->draft()->create(['subject_id' => $this->subject->id, 'title' => 'Bản nháp']);

        $student = User::factory()->student($this->subject)->create();

        $this->actingAs($student)->get(route('info'))->assertOk()->assertSee('Đã đăng')->assertDontSee('Bản nháp');
    }

    public function test_teacher_can_delete_announcement(): void
    {
        $announcement = Announcement::factory()->create(['subject_id' => $this->subject->id]);

        Livewire::test(AnnouncementsIndex::class)->call('delete', $announcement->id);

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }
}
