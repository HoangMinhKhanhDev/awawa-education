<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $nonSubmitter;

    private User $submitter;

    private Subject $subject;

    private function makeSetup(): Exam
    {
        $this->subject = Subject::factory()->create();
        User::factory()->teacher($this->subject)->create();

        $this->nonSubmitter = User::factory()->student($this->subject)->create();
        $this->submitter = User::factory()->student($this->subject)->create();

        foreach ([$this->nonSubmitter, $this->submitter] as $student) {
            TeamMembership::query()->create([
                'subject_id' => $this->subject->id,
                'student_id' => $student->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'due_at' => now()->addHours(24),
        ]);
    }

    private function markSubmitted(Exam $exam, User $student): void
    {
        ExamAttempt::create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::Graded,
            'started_at' => now(),
            'submitted_at' => now(),
            'graded_at' => now(),
            'score' => 8,
            'max_score' => 10,
        ]);
    }

    public function test_it_sends_24h_reminder_only_to_students_who_have_not_submitted(): void
    {
        $exam = $this->makeSetup();
        $this->markSubmitted($exam, $this->submitter);

        $this->artisan('awawa:due-reminders')->assertSuccessful();

        $this->assertSame(1, $this->nonSubmitter->notifications()->count());
        $this->assertSame(0, $this->submitter->notifications()->count());
        $this->assertDatabaseHas('exam_reminders', ['exam_id' => $exam->id, 'milestone' => '24h']);
        $this->assertSame('exam_due_soon', $this->nonSubmitter->notifications()->first()->data['type']);
    }

    public function test_it_does_not_send_duplicate_reminders(): void
    {
        $this->makeSetup();

        $this->artisan('awawa:due-reminders')->assertSuccessful();
        $this->artisan('awawa:due-reminders')->assertSuccessful();

        $this->assertSame(1, $this->nonSubmitter->notifications()->count());
        $this->assertDatabaseCount('exam_reminders', 1);
    }

    public function test_it_sends_1h_reminder(): void
    {
        $subject = Subject::factory()->create();
        User::factory()->teacher($subject)->create();
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'due_at' => now()->addMinutes(60),
        ]);

        $this->artisan('awawa:due-reminders')->assertSuccessful();

        $this->assertSame(1, $student->notifications()->count());
        $this->assertDatabaseHas('exam_reminders', ['exam_id' => $exam->id, 'milestone' => '1h']);
        $this->assertSame('1h', $student->notifications()->first()->data['milestone']);
    }
}
