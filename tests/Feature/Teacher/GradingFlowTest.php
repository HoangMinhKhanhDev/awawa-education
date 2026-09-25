<?php

namespace Tests\Feature\Teacher;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Livewire\Teacher\GradingIndex;
use App\Models\AttemptAnswer;
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

class GradingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_grade_essay_and_finalize_attempt(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
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
        ]);

        $essay = Question::factory()->essay()->create(['subject_id' => $subject->id, 'points' => 5]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $essay->id, 'order' => 0]);

        $attempt = ExamAttempt::create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now(),
            'submitted_at' => now(),
            'max_score' => 5,
        ]);

        $answer = AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'answer_text' => 'Lời giải tự luận.',
        ]);

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($subject->id);

        Livewire::test(GradingIndex::class, ['exam' => $exam])
            ->call('openGrading', $attempt->id)
            ->set('manual.'.$answer->id.'.points', 4)
            ->set('manual.'.$answer->id.'.feedback', 'Trình bày tốt, cần chặt chẽ hơn.')
            ->call('saveGrading')
            ->assertHasNoErrors();

        $attempt->refresh();
        $answer->refresh();

        $this->assertEquals(4.0, (float) $answer->awarded_points);
        $this->assertSame('Trình bày tốt, cần chặt chẽ hơn.', $answer->feedback);
        $this->assertEquals(4.0, (float) $attempt->manual_score);
        $this->assertEquals(4.0, (float) $attempt->score);
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertSame($teacher->id, $attempt->graded_by);
    }

    public function test_teacher_cannot_grade_other_subject_attempt(): void
    {
        $subject = Subject::factory()->create();
        $otherSubject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $student = User::factory()->student($otherSubject)->create();

        $exam = Exam::factory()->create(['subject_id' => $otherSubject->id, 'type' => ExamType::Exam, 'status' => ExamStatus::Published]);

        $attempt = ExamAttempt::create([
            'subject_id' => $otherSubject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now(),
            'max_score' => 5,
        ]);

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($subject->id);

        Livewire::test(GradingIndex::class, ['exam' => $exam])->assertForbidden();
    }
}
