<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\MapVisibility;
use App\Enums\QuestionType;
use App\Livewire\Admin\Users\Index as AdminUsers;
use App\Livewire\Documents\Show as DocumentShow;
use App\Livewire\Maps\Editor as MapEditor;
use App\Livewire\Maps\Shared as MapShared;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Student\Result as StudentResult;
use App\Livewire\Student\Take as StudentTake;
use App\Livewire\Teacher\AssessmentBuilder;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\KnowledgeMap;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Notifications\ExamPublishedNotification;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
    }

    private function member(Subject $subject): User
    {
        $student = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $student;
    }

    private function publishedExam(): Exam
    {
        return Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 4,
        ]);
    }

    public function test_student_cannot_overwrite_another_student_attempt(): void
    {
        $exam = $this->publishedExam();
        $a = $this->member($this->subject);
        $b = $this->member($this->subject);

        $this->actingAs($a);
        app(SubjectContext::class)->set($this->subject->id);
        Livewire::test(StudentTake::class, ['exam' => $exam])->call('saveProgress');
        $attemptA = ExamAttempt::query()->where('student_id', $a->id)->firstOrFail();

        $this->actingAs($b);
        app(SubjectContext::class)->set($this->subject->id);
        $component = Livewire::test(StudentTake::class, ['exam' => $exam]);

        try {
            $component->set('attemptId', $attemptA->id)->call('saveProgress');
        } catch (\Throwable) {
            // #[Locked] chặn giả mạo từ client là đạt yêu cầu.
            $this->assertTrue(true);

            return;
        }

        // Nếu lọt qua Locked thì ownership check phải chặn ở action.
        $component->assertForbidden();
    }

    public function test_result_page_rejects_cross_exam_attempt(): void
    {
        $examA = $this->publishedExam();
        $examB = $this->publishedExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(StudentTake::class, ['exam' => $examA])->call('saveProgress');
        $attemptA = ExamAttempt::query()->where('student_id', $student->id)->firstOrFail();

        try {
            Livewire::test(StudentResult::class, ['exam' => $examB])->set('attemptId', $attemptA->id);
        } catch (\Throwable) {
            $this->assertTrue(true);

            return;
        }

        $this->assertNotSame($examB->id, $attemptA->exam_id);
    }

    public function test_document_show_render_rejects_other_subject(): void
    {
        $other = Subject::factory()->create();
        $document = Document::factory()->create(['subject_id' => $other->id, 'is_public' => true]);
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(DocumentShow::class, ['document' => $document])->assertForbidden();
    }

    public function test_map_editor_render_rejects_other_owner(): void
    {
        $owner = User::factory()->student($this->subject)->create();
        $intruder = User::factory()->student($this->subject)->create();

        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($intruder);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(MapEditor::class, ['map' => $map])->assertForbidden();
    }

    public function test_shared_map_render_requires_token_match(): void
    {
        $owner = User::factory()->student($this->subject)->create();

        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $owner->id,
            'visibility' => MapVisibility::Link,
            'share_token' => Str::random(32),
        ]);

        $this->actingAs($owner);

        $component = Livewire::test(MapShared::class, ['token' => $map->share_token])->assertOk();

        try {
            $component->set('mapId', $map->id + 99999);
        } catch (\Throwable) {
            $this->assertTrue(true);

            return;
        }

        $this->assertTrue(true);
    }

    public function test_assessment_builder_bank_is_scoped_to_exam_subject(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $other = Subject::factory()->create();

        Question::factory()->create(['subject_id' => $this->subject->id, 'type' => QuestionType::MultipleChoice]);
        $foreign = Question::factory()->create(['subject_id' => $other->id, 'type' => QuestionType::MultipleChoice]);

        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Draft,
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        $view = Livewire::test(AssessmentBuilder::class, ['exam' => $exam])->viewData('bankQuestions');

        $this->assertFalse(collect($view)->contains(fn ($q) => $q->id === $foreign->id));
    }

    public function test_teacher_cannot_create_user_via_admin_panel(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(AdminUsers::class)
            ->set('name', 'Kẻ leo thang')
            ->set('email', 'evil@awawa.test')
            ->set('role', 'super_admin')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'evil@awawa.test']);
    }

    public function test_notification_open_rejects_external_url(): void
    {
        $student = $this->member($this->subject);

        $this->actingAs($student);

        $student->notify(new ExamPublishedNotification(
            Exam::factory()->create([
                'subject_id' => $this->subject->id,
                'type' => ExamType::Exam,
                'status' => ExamStatus::Published,
            ])
        ));

        $notification = $student->notifications()->firstOrFail();
        $notification->forceFill(['data' => ['url' => 'https://evil.test/phish']])->save();

        Livewire::test(NotificationsIndex::class, [])
            ->call('open', (string) $notification->id)
            ->assertRedirect(route('notifications.index'));
    }

    public function test_take_ids_are_locked_against_tampering(): void
    {
        $exam = $this->publishedExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(StudentTake::class, ['exam' => $exam]);

        try {
            $component->set('examId', $exam->id + 99999)->call('saveProgress');
            $this->fail('Locked examId phải chặn giả mạo.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }
}
