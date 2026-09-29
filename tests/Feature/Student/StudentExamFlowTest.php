<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Livewire\Student\Result;
use App\Livewire\Student\Take;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
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

    /**
     * @return array{0: Exam, 1: Question, 2: QuestionOption, 3: QuestionOption}
     */
    private function makeRetakeableExam(int $maxAttempts): array
    {
        [$exam, $question, $correct, $wrong] = $this->makeExam();

        $exam->forceFill(['settings' => ['max_attempts' => $maxAttempts]])->save();

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

    /**
     * @return array{0: Exam, 1: ExamSection, 2: array<int, Question>}
     */
    private function makeTrueFalseCluster(): array
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Exam,
            'status' => ExamStatus::Published,
            'total_points' => 4,
        ]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'PHẦN II',
            'instructions' => 'Rừng là lá phổi xanh của Trái Đất.',
            'order' => 0,
        ]);

        $questions = [];

        foreach (['true', 'false', 'true', 'false'] as $index => $answer) {
            $question = Question::factory()->create([
                'subject_id' => $this->subject->id,
                'type' => QuestionType::TrueFalse,
                'points' => 1,
                'answer' => $answer,
            ]);

            ExamQuestion::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section->id,
                'question_id' => $question->id,
                'order' => $index,
                'points' => 1,
            ]);

            $questions[] = $question;
        }

        return [$exam, $section, $questions];
    }

    public function test_take_shows_the_shared_passage_before_its_cluster(): void
    {
        [$exam] = $this->makeTrueFalseCluster();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->assertSee('PHẦN II')
            ->assertSee('Rừng là lá phổi xanh của Trái Đất.');
    }

    public function test_true_false_cluster_scores_each_statement_independently(): void
    {
        [$exam, , $questions] = $this->makeTrueFalseCluster();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        // Đúng 3/4 mệnh đề thì được 3 điểm, không mất trắng.
        $component = Livewire::test(Take::class, ['exam' => $exam]);

        $answers = ['true', 'false', 'true', 'true'];

        foreach ($questions as $index => $question) {
            $component->set('answers.'.$question->id.'.text', $answers[$index]);
        }

        $component->call('submit')->assertRedirect(route('student.result', $exam));

        $attempt = ExamAttempt::query()->where('exam_id', $exam->id)->firstOrFail();

        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertEquals(3.0, (float) $attempt->score);
    }

    public function test_result_shows_the_shared_passage(): void
    {
        [$exam, , $questions] = $this->makeTrueFalseCluster();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam]);

        foreach ($questions as $question) {
            $component->set('answers.'.$question->id.'.text', 'true');
        }

        $component->call('submit');

        Livewire::test(Result::class, ['exam' => $exam])
            ->assertSee('PHẦN II')
            ->assertSee('Rừng là lá phổi xanh của Trái Đất.');
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

    public function test_student_can_retake_when_exam_allows_multiple_attempts(): void
    {
        [$exam, $question, $correct] = $this->makeRetakeableExam(3);
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        $attempts = ExamAttempt::query()->where('exam_id', $exam->id)->orderBy('attempt_no')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame([1, 2], $attempts->pluck('attempt_no')->all());
        $this->assertSame(2, $exam->fresh()->attemptsUsedBy($student));
        $this->assertTrue($exam->fresh()->canAttemptAgain($student));
        $this->assertSame(1, $exam->fresh()->remainingAttemptsFor($student));
    }

    public function test_student_cannot_retake_when_attempts_are_exhausted(): void
    {
        [$exam, $question, $correct] = $this->makeRetakeableExam(1);
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        Livewire::test(Take::class, ['exam' => $exam])->assertRedirect(route('student.result', $exam));

        $this->assertSame(1, ExamAttempt::query()->where('exam_id', $exam->id)->count());
        $this->assertFalse($exam->fresh()->canAttemptAgain($student));
    }

    public function test_in_progress_attempt_is_resumed_instead_of_creating_a_new_one(): void
    {
        [$exam, $question, $correct] = $this->makeRetakeableExam(3);
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])->assertOk();

        $attemptId = ExamAttempt::query()->where('exam_id', $exam->id)->value('id');

        Livewire::test(Take::class, ['exam' => $exam])->assertOk();

        $this->assertSame(1, ExamAttempt::query()->where('exam_id', $exam->id)->count());
        $this->assertSame($attemptId, ExamAttempt::query()->where('exam_id', $exam->id)->value('id'));
    }

    public function test_result_can_show_a_previous_attempt(): void
    {
        [$exam, $question, $correct, $wrong] = $this->makeRetakeableExam(2);
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        // Lần một trả lời sai, lần hai trả lời đúng.
        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $wrong->id)
            ->call('submit');

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        Livewire::test(Result::class, ['exam' => $exam])
            ->assertOk()
            ->assertSee('Lần 2/2')
            ->assertSee('+100.00')
            ->assertSee('điểm so với lần trước');

        Livewire::test(Result::class, ['exam' => $exam, 'attemptNo' => 1])
            ->assertOk()
            ->assertSee('Lần 1/2')
            ->assertDontSee('điểm so với lần trước');
    }

    public function test_time_spent_seconds_is_recorded_on_submit(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        $attempt = ExamAttempt::query()->where('exam_id', $exam->id)->firstOrFail();

        $this->assertNotNull($attempt->time_spent_seconds);
        $this->assertGreaterThanOrEqual(0, $attempt->time_spent_seconds);
    }

    public function test_repeated_anti_cheat_events_of_the_same_type_are_collapsed(): void
    {
        [$exam] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam]);

        $component->call('logAntiCheat', 'tab_hidden')
            ->call('logAntiCheat', 'tab_hidden')
            ->call('logAntiCheat', 'tab_hidden')
            ->assertSet('violations', 1);

        $this->assertCount(1, $this->attemptFor($exam)->anti_cheat);
    }

    public function test_anti_cheat_events_of_different_types_are_all_kept(): void
    {
        [$exam] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        Livewire::test(Take::class, ['exam' => $exam])
            ->call('logAntiCheat', 'tab_hidden')
            ->call('logAntiCheat', 'fullscreen_exit')
            ->assertSet('violations', 2);

        $this->assertCount(2, $this->attemptFor($exam)->anti_cheat);
    }

    public function test_anti_cheat_event_is_kept_again_after_the_dedupe_window(): void
    {
        [$exam] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam]);

        $component->call('logAntiCheat', 'tab_hidden');

        $this->travel(61)->seconds();

        $component->call('logAntiCheat', 'tab_hidden')
            ->assertSet('violations', 2);

        $this->assertCount(2, $this->attemptFor($exam)->anti_cheat);
    }

    public function test_anti_cheat_is_ignored_once_the_attempt_is_finished(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam]);

        $component->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('submit');

        $component->call('logAntiCheat', 'tab_hidden');

        $this->assertCount(0, $this->attemptFor($exam)->anti_cheat);
    }

    public function test_save_progress_only_persists_answers_that_actually_changed(): void
    {
        [$exam, $question, $correct] = $this->makeExam();
        $student = $this->member($this->subject);

        $this->actingAs($student);
        app(SubjectContext::class)->set($this->subject->id);

        $component = Livewire::test(Take::class, ['exam' => $exam]);

        $component->set('answers.'.$question->id.'.selected', $correct->id)
            ->call('saveProgress');

        $this->assertSame(
            [$correct->id],
            $this->answerFor($exam, $question)->selected_option_ids,
        );

        $updatedAt = $this->answerFor($exam, $question)->updated_at;

        $this->travel(10)->seconds();

        // Gọi lại mà không đổi gì: dòng đã đúng thì không được ghi đè.
        $component->call('saveProgress');

        $this->assertTrue(
            $updatedAt->equalTo($this->answerFor($exam, $question)->updated_at),
            'Dòng trả lời không đổi thì không nên bị ghi lại.',
        );
    }

    private function attemptFor(Exam $exam): ExamAttempt
    {
        return ExamAttempt::query()->where('exam_id', $exam->id)->firstOrFail();
    }

    private function answerFor(Exam $exam, Question $question): AttemptAnswer
    {
        return AttemptAnswer::query()
            ->where('attempt_id', $this->attemptFor($exam)->id)
            ->where('question_id', $question->id)
            ->firstOrFail();
    }
}
