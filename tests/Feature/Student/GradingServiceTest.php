<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_grades_multiple_choice_fill_blank_and_defers_essay(): void
    {
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $mc = Question::factory()->create(['subject_id' => $subject->id, 'type' => QuestionType::MultipleChoice, 'points' => 4]);
        $correct = $mc->options()->create(['content' => 'Đúng', 'is_correct' => true, 'order' => 0]);
        $mc->options()->create(['content' => 'Sai', 'is_correct' => false, 'order' => 1]);

        $fill = Question::factory()->create(['subject_id' => $subject->id, 'type' => QuestionType::FillBlank, 'points' => 2, 'answer' => 'Hà Nội']);

        $essay = Question::factory()->create(['subject_id' => $subject->id, 'type' => QuestionType::Essay, 'points' => 4]);

        foreach ([$mc, $fill, $essay] as $index => $question) {
            ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => $index]);
        }

        $student = User::factory()->student($subject)->create();

        $attempt = ExamAttempt::create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'max_score' => 10,
        ]);

        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $mc->id, 'selected_option_ids' => [$correct->id]]);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $fill->id, 'answer_text' => '  hà   nội ']);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $essay->id, 'answer_text' => 'Bài luận dài...']);

        app(GradingService::class)->gradeAttempt($attempt);

        $attempt->refresh();

        $this->assertTrue($attempt->answers()->where('question_id', $mc->id)->first()->is_correct);
        $this->assertTrue($attempt->answers()->where('question_id', $fill->id)->first()->is_correct);
        $this->assertNull($attempt->answers()->where('question_id', $essay->id)->first()->awarded_points);

        $this->assertEquals(6.0, (float) $attempt->auto_score);
        $this->assertEquals(6.0, (float) $attempt->score);
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertTrue($attempt->hasPendingManualGrading());
    }

    public function test_it_marks_graded_when_no_manual_questions(): void
    {
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->create(['subject_id' => $subject->id, 'type' => ExamType::Exam, 'status' => ExamStatus::Published]);
        $question = Question::factory()->create(['subject_id' => $subject->id, 'type' => QuestionType::MultipleChoice, 'points' => 5]);
        $correct = $question->options()->create(['content' => 'A', 'is_correct' => true, 'order' => 0]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $student = User::factory()->student($subject)->create();

        $attempt = ExamAttempt::create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'max_score' => 5,
        ]);

        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_ids' => [$correct->id]]);

        app(GradingService::class)->gradeAttempt($attempt);

        $attempt->refresh();

        $this->assertEquals(5.0, (float) $attempt->score);
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertFalse($attempt->hasPendingManualGrading());
    }

    public function test_wrong_answer_scores_zero(): void
    {
        $subject = Subject::factory()->create();
        $exam = Exam::factory()->create(['subject_id' => $subject->id, 'type' => ExamType::Exam, 'status' => ExamStatus::Published]);
        $question = Question::factory()->create(['subject_id' => $subject->id, 'type' => QuestionType::FillBlank, 'points' => 3, 'answer' => 'Hà Nội']);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $student = User::factory()->student($subject)->create();

        $attempt = ExamAttempt::create([
            'subject_id' => $subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'max_score' => 3,
        ]);

        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer_text' => 'Đà Nẵng']);

        app(GradingService::class)->gradeAttempt($attempt);

        $attempt->refresh();

        $this->assertFalse($attempt->answers()->first()->is_correct);
        $this->assertEquals(0.0, (float) $attempt->score);
    }
}
