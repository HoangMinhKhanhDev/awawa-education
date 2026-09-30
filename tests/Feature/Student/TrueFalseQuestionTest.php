<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Livewire\Student\Result;
use App\Livewire\Student\Take;
use App\Livewire\Teacher\QuestionsIndex;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Services\GradingService;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrueFalseQuestionTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
    }

    private function trueFalseQuestion(string $answer = QuestionType::TRUE): Question
    {
        return Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => QuestionType::TrueFalse,
            'answer' => $answer,
            'points' => 2,
        ]);
    }

    private function member(): User
    {
        $student = User::factory()->student($this->subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $this->subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $student;
    }

    public function test_it_is_part_of_the_supported_types(): void
    {
        $this->assertContains(QuestionType::TrueFalse->value, QuestionType::values());
        $this->assertSame('Đúng / sai', QuestionType::TrueFalse->label());
        $this->assertFalse(QuestionType::TrueFalse->hasOptions());
        $this->assertTrue(QuestionType::TrueFalse->requiresAnswer());
    }

    public function test_it_normalizes_every_spelling_of_the_answer(): void
    {
        foreach (['Đúng', 'đúng', 'DUNG', 'true', '1', 'có'] as $variant) {
            $this->assertSame(QuestionType::TRUE, QuestionType::normalizeTruthy($variant), $variant);
        }

        foreach (['Sai', 'sai', 'false', '0', 'không', 'no'] as $variant) {
            $this->assertSame(QuestionType::FALSE, QuestionType::normalizeTruthy($variant), $variant);
        }

        $this->assertNull(QuestionType::normalizeTruthy('có lẽ'));
        $this->assertNull(QuestionType::normalizeTruthy(null));

        $this->assertSame('Đúng', QuestionType::TrueFalse->trueFalseLabel('Đúng'));
        $this->assertSame('Sai', QuestionType::TrueFalse->trueFalseLabel('sai'));
        $this->assertSame('Chưa chọn', QuestionType::TrueFalse->trueFalseLabel(null));
        $this->assertSame('Chưa chọn', QuestionType::TrueFalse->trueFalseLabel(''));
    }

    public function test_grading_accepts_both_spellings_and_rejects_the_wrong_side(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $question = $this->trueFalseQuestion(QuestionType::TRUE);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $student = User::factory()->student($this->subject)->create();

        $attemptNo = 0;

        $grade = function (string $given) use ($exam, $question, $student, &$attemptNo): bool {
            $attempt = ExamAttempt::create([
                'subject_id' => $this->subject->id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'max_score' => 2,
                'attempt_no' => ++$attemptNo,
            ]);

            AttemptAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'answer_text' => $given,
            ]);

            app(GradingService::class)->gradeAttempt($attempt);

            return (bool) $attempt->fresh()->answers()->firstOrFail()->is_correct;
        };

        $this->assertTrue($grade('true'), 'Giá trị chuẩn');
        $this->assertTrue($grade('Đúng'), 'Tiếng Việt viết hoa');
        $this->assertFalse($grade('sai'), 'Chọn sai');
        $this->assertFalse($grade(''), 'Bỏ trống');
        $this->assertFalse($grade('không rõ'), 'Trả lời ngoài lựa chọn');
    }

    public function test_teacher_can_create_a_true_false_question(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::TrueFalse->value)
            ->set('content', 'Trái đất quay quanh Mặt Trời.')
            ->set('answer', QuestionType::TRUE)
            ->set('explanation', 'Sai vì quay quanh Mặt Trời là một năm.')
            ->call('save')
            ->assertHasNoErrors();

        $question = Question::query()->firstOrFail();

        $this->assertSame(QuestionType::TrueFalse, $question->type);
        $this->assertSame(QuestionType::TRUE, $question->answer);
        $this->assertSame(0, $question->options()->count(), 'Câu đúng/sai không có lựa chọn');
    }

    public function test_saving_without_picking_a_side_is_rejected(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::TrueFalse->value)
            ->set('content', 'Một mệnh đề bất kỳ.')
            ->set('answer', '')
            ->call('save')
            ->assertHasErrors(['answer']);

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_switching_type_clears_the_answer_of_the_previous_type(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(QuestionsIndex::class)->call('openCreate');

        $component->set('type', QuestionType::TrueFalse->value)
            ->assertSet('answer', QuestionType::FALSE)
            ->set('answer', QuestionType::TRUE)
            ->set('type', QuestionType::Essay->value)
            ->assertSet('answer', '');
    }

    public function test_editing_normalizes_an_answer_written_by_ai(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $question = $this->trueFalseQuestion('Đúng');

        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(QuestionsIndex::class)
            ->call('openEdit', $question->id)
            ->assertSet('answer', QuestionType::TRUE)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(QuestionType::TRUE, $question->fresh()->answer);
    }

    public function test_student_answers_and_sees_the_result(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 2,
        ]);

        $question = $this->trueFalseQuestion(QuestionType::TRUE);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $student = $this->member();

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->assertSee('Đúng')
            ->assertSee('Sai')
            ->set('answers.'.$question->id.'.text', QuestionType::FALSE)
            ->call('submit')
            ->assertRedirect(route('student.result', $exam));

        $attempt = ExamAttempt::query()->where('exam_id', $exam->id)->firstOrFail();

        $this->assertSame(0.0, (float) $attempt->score);
        $this->assertFalse((bool) $attempt->answers()->firstOrFail()->is_correct);

        // Trang kết quả phải hiện nhãn tiếng Việt chứ không phải "true"/"false".
        Livewire::test(Result::class, ['exam' => $exam])
            ->assertOk()
            ->assertSee('Bạn chọn')
            ->assertSee('Đáp án:')
            ->assertDontSee('true', escape: false);
    }

    public function test_saving_progress_persists_the_side_chosen(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
        ]);

        $question = $this->trueFalseQuestion();
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0]);

        $student = $this->member();

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.text', QuestionType::FALSE)
            ->call('saveProgress');

        $this->assertSame(
            QuestionType::FALSE,
            AttemptAnswer::query()->firstOrFail()->answer_text,
        );
    }

    /**
     * @return array{0: Exam, 1: Question}
     */
    private function trueFalseCluster(array $truths = [true, true, false, false]): array
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 1,
        ]);

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => QuestionType::TrueFalseCluster,
            'content' => 'Rừng là lá phổi xanh của Trái Đất.',
            'points' => 1,
        ]);

        foreach ($truths as $index => $truth) {
            $question->options()->create([
                'content' => 'Mệnh đề '.chr(97 + $index),
                'is_correct' => $truth,
                'order' => $index,
            ]);
        }

        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'order' => 0, 'points' => 1]);

        return [$exam, $question];
    }

    /**
     * @param  array<int, string|null>  $subs
     */
    private function gradeCluster(Exam $exam, Question $question, User $student, array $subs): float
    {
        $attempt = ExamAttempt::create([
            'subject_id' => $this->subject->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'max_score' => 1,
            'attempt_no' => ExamAttempt::query()->where('exam_id', $exam->id)->where('student_id', $student->id)->count() + 1,
        ]);

        AttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'sub_answers' => $subs,
        ]);

        app(GradingService::class)->gradeAttempt($attempt);

        return (float) $attempt->fresh()->score;
    }

    public function test_cluster_follows_the_bgd_partial_scoring_table(): void
    {
        [$exam, $question] = $this->trueFalseCluster();
        $student = $this->member();

        // Đáp án đúng: true, true, false, false.
        $this->assertSame(1.0, $this->gradeCluster($exam, $question, $student, ['true', 'true', 'false', 'false']));
        $this->assertSame(0.5, $this->gradeCluster($exam, $question, $student, ['true', 'true', 'false', 'true']));
        $this->assertSame(0.25, $this->gradeCluster($exam, $question, $student, ['true', 'true', 'true', 'true']));
        $this->assertSame(0.0, $this->gradeCluster($exam, $question, $student, ['true', 'false', 'true', 'true']));
        $this->assertSame(0.0, $this->gradeCluster($exam, $question, $student, ['false', 'false', 'true', 'true']));

        // Bỏ trống 1 mệnh đề tính là sai: đúng 3/4 vẫn 0.5.
        $this->assertSame(0.5, $this->gradeCluster($exam, $question, $student, ['true', 'true', 'false', null]));
    }

    public function test_student_answers_a_cluster_through_take_and_sees_it_in_result(): void
    {
        [$exam, $question] = $this->trueFalseCluster();
        $student = $this->member();

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam])
            ->assertSee('Rừng là lá phổi xanh')
            ->assertSee('Mệnh đề a');

        foreach (['true', 'true', 'false', 'false'] as $index => $value) {
            $component->set('answers.'.$question->id.'.subs.'.$index, $value);
        }

        $component->call('submit')->assertRedirect(route('student.result', $exam));

        $this->assertSame(1.0, (float) ExamAttempt::query()->firstOrFail()->score);

        Livewire::test(Result::class, ['exam' => $exam])
            ->assertOk()
            ->assertSee('Rừng là lá phổi xanh')
            ->assertSee('Đúng 4/4 mệnh đề');
    }

    public function test_teacher_can_create_a_cluster_manually(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::TrueFalseCluster->value)
            ->set('content', 'Đoạn ngữ cảnh chung.')
            ->set('points', 1);

        foreach (['Mệnh đề a', 'Mệnh đề b', 'Mệnh đề c', 'Mệnh đề d'] as $index => $content) {
            $component->set('options.'.$index.'.content', $content);
        }

        $component->call('markTruth', 0)->call('markTruth', 1)->call('save')->assertHasNoErrors();

        $question = Question::query()->firstOrFail();

        $this->assertSame(QuestionType::TrueFalseCluster, $question->type);
        $this->assertSame(
            [true, true, false, false],
            $question->options()->orderBy('order')->pluck('is_correct')->map(fn ($value): bool => (bool) $value)->all(),
        );
    }

    public function test_cluster_requires_exactly_four_statements(): void
    {
        $teacher = User::factory()->teacher($this->subject)->create();
        $this->actingAs($teacher);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::TrueFalseCluster->value)
            ->set('content', 'Đoạn ngữ cảnh chung.')
            ->set('options.0.content', 'Chỉ một mệnh đề')
            ->call('save')
            ->assertHasErrors(['options']);

        $this->assertDatabaseCount('questions', 0);
    }
}
