<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Livewire\Teacher\AssessmentBuilder;
use App\Models\Announcement;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->teacher = User::factory()->teacher($this->subject)->create();
        $this->student = User::factory()->student($this->subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_exam_publish_notifies_team_members(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Draft,
        ]);

        app(NotificationDispatcher::class)->examPublished($exam);

        $this->assertSame(1, $this->student->notifications()->count());
        $this->assertSame('exam_published', $this->student->notifications()->first()->data['type']);
    }

    public function test_announcement_publish_notifies_members(): void
    {
        $announcement = Announcement::factory()->create(['subject_id' => $this->subject->id]);

        app(NotificationDispatcher::class)->announcementPublished($announcement);

        $this->assertSame(1, $this->student->notifications()->count());
        $this->assertSame('announcement', $this->student->notifications()->first()->data['type']);
    }

    public function test_grading_notifies_student(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        $attempt = ExamAttempt::create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => AttemptStatus::Graded,
            'score' => 8,
            'max_score' => 10,
        ]);

        app(NotificationDispatcher::class)->attemptGraded($attempt);

        $this->assertSame(1, $this->student->notifications()->count());
        $this->assertSame('attempt_graded', $this->student->notifications()->first()->data['type']);
    }

    public function test_publishing_via_builder_dispatches_notification(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Draft,
        ]);

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => QuestionType::MultipleChoice,
        ]);

        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $this->actingAs($this->teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->call('changeStatus', ExamStatus::Published->value);

        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);
        $this->assertSame(1, $this->student->notifications()->count());
    }

    public function test_notifications_page_renders(): void
    {
        app(NotificationDispatcher::class)->examPublished(
            Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]),
        );

        $this->actingAs($this->student)->get(route('notifications.index'))->assertOk();
    }
}
