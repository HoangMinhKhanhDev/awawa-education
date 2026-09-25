<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\StudentsIndex;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentTeamTest extends TestCase
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

    public function test_teacher_can_add_unassigned_student_to_team(): void
    {
        $student = User::factory()->student()->create();

        Livewire::test(StudentsIndex::class)->call('addStudent', $student->id);

        $this->assertSame($this->subject->id, $student->fresh()->subject_id);
        $this->assertTrue($student->fresh()->isActiveMemberOf($this->subject->id));
    }

    public function test_teacher_cannot_add_student_from_another_subject(): void
    {
        $otherSubject = Subject::factory()->create();
        $foreign = User::factory()->student($otherSubject)->create();

        Livewire::test(StudentsIndex::class)->call('addStudent', $foreign->id);

        $this->assertSame($otherSubject->id, $foreign->fresh()->subject_id);
        $this->assertSame(0, TeamMembership::query()->withoutSubjectScope()->where('student_id', $foreign->id)->count());
    }

    public function test_teacher_can_remove_student_from_team(): void
    {
        $student = User::factory()->student($this->subject)->create();
        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Livewire::test(StudentsIndex::class)->call('removeStudent', $student->id);

        $this->assertFalse($student->fresh()->isActiveMemberOf($this->subject->id));
    }

    public function test_teacher_can_create_student_account_directly(): void
    {
        Livewire::test(StudentsIndex::class)
            ->call('openCreate')
            ->set('newName', 'Học sinh mới')
            ->set('newEmail', 'moi@awawa.test')
            ->call('createStudent')
            ->assertHasNoErrors();

        $student = User::query()->where('email', 'moi@awawa.test')->firstOrFail();

        $this->assertSame($this->subject->id, $student->subject_id);
        $this->assertTrue($student->isActiveMemberOf($this->subject->id));
    }

    public function test_student_cannot_open_management_page(): void
    {
        $student = User::factory()->student($this->subject)->create();

        $this->actingAs($student)->get(route('students'))->assertForbidden();
    }
}
