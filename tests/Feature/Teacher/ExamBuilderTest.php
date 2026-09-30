<?php

namespace Tests\Feature\Teacher;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Livewire\Teacher\AssessmentBuilder;
use App\Livewire\Teacher\AssignmentsIndex;
use App\Livewire\Teacher\ExamsIndex;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamBuilderTest extends TestCase
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

    public function test_teacher_can_create_exam_and_open_builder(): void
    {
        Livewire::test(ExamsIndex::class)
            ->call('openCreate')
            ->set('title', 'Kiểm tra chuyên đề 1')
            ->call('create')
            ->assertHasNoErrors();

        $exam = Exam::query()->where('title', 'Kiểm tra chuyên đề 1')->firstOrFail();

        $this->assertSame($this->subject->id, $exam->subject_id);
        $this->assertSame(ExamType::Exam, $exam->type);
        $this->assertSame(ExamStatus::Draft, $exam->status);
    }

    public function test_new_exam_defaults_to_one_attempt_but_assignment_to_three(): void
    {
        Livewire::test(ExamsIndex::class)
            ->call('openCreate')
            ->set('title', 'Kiểm tra chuyên đề 1')
            ->call('create');

        $exam = Exam::query()->where('title', 'Kiểm tra chuyên đề 1')->firstOrFail();

        $this->assertSame(1, $exam->maxAttempts());
        $this->assertFalse($exam->allowsRetake());

        Livewire::test(AssignmentsIndex::class)
            ->call('openCreate')
            ->set('title', 'Bài tập tuần 1')
            ->call('create');

        $assignment = Exam::query()->where('title', 'Bài tập tuần 1')->firstOrFail();

        $this->assertSame(3, $assignment->maxAttempts());
        $this->assertTrue($assignment->allowsRetake());
    }

    public function test_teacher_can_set_max_attempts_in_builder(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Assignment,
            'status' => ExamStatus::Draft,
        ]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('title', $exam->title)
            ->set('maxAttempts', 5)
            ->call('saveMeta')
            ->assertHasNoErrors();

        $this->assertSame(5, $exam->fresh()->maxAttempts());
    }

    public function test_max_attempts_cannot_be_below_one(): void
    {
        $exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => ExamType::Assignment,
            'status' => ExamStatus::Draft,
        ]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('title', $exam->title)
            ->set('maxAttempts', 0)
            ->call('saveMeta')
            ->assertHasErrors(['maxAttempts']);

        $this->assertSame(1, $exam->fresh()->maxAttempts());
    }

    public function test_builder_adds_questions_and_computes_total_points(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);
        $q1 = Question::factory()->create(['subject_id' => $this->subject->id, 'points' => 2]);
        $q2 = Question::factory()->create(['subject_id' => $this->subject->id, 'points' => 3]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('selectedQuestions', [$q1->id, $q2->id])
            ->call('addQuestions')
            ->assertHasNoErrors();

        $exam->refresh();

        $this->assertSame(2, $exam->examQuestions()->count());
        $this->assertEquals(5.0, (float) $exam->total_points);
    }

    public function test_cannot_publish_without_questions(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->call('changeStatus', ExamStatus::Published->value);

        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_publish_and_close_flow(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);
        $question = Question::factory()->create(['subject_id' => $this->subject->id]);

        $component = Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('selectedQuestions', [$question->id])
            ->call('addQuestions');

        $component->call('changeStatus', ExamStatus::Published->value);
        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);

        $component->call('changeStatus', ExamStatus::Closed->value);
        $this->assertSame(ExamStatus::Closed, $exam->fresh()->status);
    }

    public function test_sections_can_be_added(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('newSectionTitle', 'Phần I. Trắc nghiệm')
            ->call('addSection');

        $this->assertDatabaseHas('exam_sections', [
            'exam_id' => $exam->id,
            'title' => 'Phần I. Trắc nghiệm',
        ]);
    }

    public function test_teacher_cannot_open_other_subject_exam_builder(): void
    {
        $otherSubject = Subject::factory()->create();
        $foreign = Exam::factory()->create(['subject_id' => $otherSubject->id, 'type' => ExamType::Exam]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $foreign])->assertForbidden();
    }

    public function test_teacher_can_create_true_false_cluster_in_builder(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        $component = Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->set('newSectionTitle', 'Phần II. Đúng/Sai')
            ->call('addSection');

        $section = $exam->sections()->firstOrFail();

        $component
            ->call('openClusterForm', $section->id)
            ->set('clusterContent', 'Theo bảng số liệu bên dưới.')
            ->set('clusterStatements', [
                ['content' => 'Nông sản tăng liên tục', 'is_correct' => true],
                ['content' => 'Xuất khẩu giảm sút', 'is_correct' => false],
                ['content' => 'Giá cả ổn định', 'is_correct' => true],
                ['content' => 'Nhập khẩu giảm', 'is_correct' => false],
            ])
            ->set('clusterPoints', 1)
            ->call('saveCluster')
            ->assertHasNoErrors()
            ->assertSet('showClusterForm', false);

        $question = Question::query()->where('type', QuestionType::TrueFalseCluster->value)->firstOrFail();

        $this->assertSame('Theo bảng số liệu bên dưới.', $question->content);
        $this->assertSame(
            [true, false, true, false],
            $question->options()->orderBy('order')->get()
                ->map(fn (QuestionOption $option): bool => (bool) $option->is_correct)->all(),
        );

        $examQuestion = $exam->examQuestions()->firstOrFail();

        $this->assertSame($section->id, $examQuestion->exam_section_id);
        $this->assertEquals(1.0, (float) $examQuestion->points);
        $this->assertEquals(1.0, (float) $exam->fresh()->total_points);
    }

    public function test_cluster_save_rejects_a_missing_statement(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->call('openClusterForm', 0)
            ->set('clusterContent', 'Ngữ cảnh chung.')
            ->set('clusterStatements', [
                ['content' => 'Mệnh đề a', 'is_correct' => true],
                ['content' => 'Mệnh đề b', 'is_correct' => false],
                ['content' => '', 'is_correct' => false],
                ['content' => 'Mệnh đề d', 'is_correct' => true],
            ])
            ->call('saveCluster')
            ->assertHasErrors(['clusterStatements.2.content']);

        $this->assertSame(0, Question::query()->where('type', QuestionType::TrueFalseCluster->value)->count());
        $this->assertSame(0, $exam->examQuestions()->count());
    }

    public function test_teacher_can_edit_an_existing_cluster_without_creating_a_duplicate(): void
    {
        $exam = Exam::factory()->create(['subject_id' => $this->subject->id, 'type' => ExamType::Exam]);

        $component = Livewire::test(AssessmentBuilder::class, ['exam' => $exam])
            ->call('openClusterForm', 0)
            ->set('clusterContent', 'Ngữ cảnh gốc.')
            ->set('clusterStatements', [
                ['content' => 'Mệnh đề a', 'is_correct' => true],
                ['content' => 'Mệnh đề b', 'is_correct' => false],
                ['content' => 'Mệnh đề c', 'is_correct' => true],
                ['content' => 'Mệnh đề d', 'is_correct' => false],
            ])
            ->set('clusterPoints', 1)
            ->call('saveCluster')
            ->assertHasNoErrors();

        $examQuestion = $exam->examQuestions()->firstOrFail();

        $component
            ->call('editCluster', $examQuestion->id)
            ->assertSet('clusterExamQuestionId', $examQuestion->id)
            ->assertSet('clusterContent', 'Ngữ cảnh gốc.')
            ->set('clusterContent', 'Ngữ cảnh đã sửa.')
            ->set('clusterPoints', 2)
            ->set('clusterStatements.0.content', 'Mệnh đề a mới')
            ->call('saveCluster')
            ->assertHasNoErrors();

        $this->assertSame(1, Question::query()->where('type', QuestionType::TrueFalseCluster->value)->count());

        $question = $exam->examQuestions()->firstOrFail()->question;

        $this->assertSame('Ngữ cảnh đã sửa.', $question->content);
        $this->assertSame('Mệnh đề a mới', $question->options()->orderBy('order')->first()->content);
        $this->assertEquals(2.0, (float) $exam->examQuestions()->firstOrFail()->points);
        $this->assertEquals(2.0, (float) $exam->fresh()->total_points);
    }
}
