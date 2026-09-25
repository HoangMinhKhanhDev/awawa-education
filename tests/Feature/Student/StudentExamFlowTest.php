<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Livewire\Student\Result;
use App\Livewire\Student\Take;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
    }

    private function makeExam(): array
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 4,
        ]);

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => QuestionType::MultipleChoice,
            'points' => 4,
        ]);

        $correct = $question->options()->create(['content' => 'Đúng', 'is_correct' => true, 'order' => 0]);
        $wrong = $question->options()->create(['content' => 'Sai', 'is_correct' => false, 'order' => 1]);

        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        return [$exam, $question, $correct, $wrong];
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

    public function test_member_can_take_and_submit_exam(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit')
            ->assertRedirect(route('student.result', $exam));

        $attempt = ExamAttempt::query()->where('exam_id', $exam->id)->firstOrFail();

        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertEquals(4.0, (float) $attempt->score);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_student_can_view_result(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        Livewire::test(Result::class, ['exam' => $exam])->assertOk()->assertSee($exam->title);
    }

    public function test_non_member_cannot_open_exam(): void
    {
        [$exam] = $this->makeExam();
        $student = User::factory()->student($this->subject)->create();

        $this->actingAs($student)->get(route('student.take', $exam))->assertForbidden();
    }

    public function test_draft_exam_cannot_be_taken(): void
    {
        [$exam, $question] = $this->makeExam();
        $exam->forceFill(['status' => ExamStatus::Draft])->save();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])->assertForbidden();
    }

    public function test_resuming_finished_attempt_redirects_to_result(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        Livewire::test(Take::class, ['exam' => $exam])->assertRedirect(route('student.result', $exam));
    }
}
