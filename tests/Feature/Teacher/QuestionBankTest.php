<?php

namespace Tests\Feature\Teacher;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Teacher\QuestionsIndex;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionBankTest extends TestCase
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

    public function test_teacher_can_create_multiple_choice_question(): void
    {
        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::MultipleChoice->value)
            ->set('content', '2 + 2 bằng bao nhiêu?')
            ->set('points', 1)
            ->set('options', [
                ['content' => '3', 'is_correct' => false],
                ['content' => '4', 'is_correct' => true],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $question = Question::query()->where('subject_id', $this->subject->id)->firstOrFail();

        $this->assertSame($this->subject->id, $question->subject_id);
        $this->assertSame($this->teacher->id, $question->created_by);
        $this->assertCount(2, $question->options);
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
    }

    public function test_multiple_choice_requires_two_options_and_correct_answer(): void
    {
        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::MultipleChoice->value)
            ->set('content', 'Câu hỏi thiếu đáp án?')
            ->set('options', [
                ['content' => 'A', 'is_correct' => false],
                ['content' => 'B', 'is_correct' => false],
            ])
            ->call('save')
            ->assertHasErrors('options');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_fill_blank_requires_answer(): void
    {
        Livewire::test(QuestionsIndex::class)
            ->call('openCreate')
            ->set('type', QuestionType::FillBlank->value)
            ->set('content', 'Thủ đô Việt Nam là ______')
            ->set('answer', '')
            ->call('save')
            ->assertHasErrors('answer');
    }

    public function test_teacher_cannot_manage_question_of_another_subject(): void
    {
        $otherSubject = Subject::factory()->create();
        $foreign = Question::factory()->create(['subject_id' => $otherSubject->id]);

        $this->assertFalse($this->teacher->can('update', $foreign));
        $this->assertFalse($this->teacher->can('delete', $foreign));
    }

    public function test_student_cannot_open_question_bank(): void
    {
        $student = User::factory()->student($this->subject)->create();

        $this->actingAs($student)->get(route('studio.questions'))->assertForbidden();
    }

    public function test_role_guard_blocks_non_teacher(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // Super admin vẫn phải có quyền giáo viên để vào studio theo middleware role.
        $this->assertSame(Role::SuperAdmin, $admin->role);
        $this->actingAs($admin)->get(route('studio'))->assertForbidden();
    }
}
