<?php

namespace Tests\Feature\Teacher;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Livewire\Teacher\AssessmentBuilder;
use App\Livewire\Teacher\ExamsIndex;
use App\Models\Exam;
use App\Models\Question;
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
}
