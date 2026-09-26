<?php

namespace Tests\Feature\Notebook;

use App\Enums\SubjectFeature;
use App\Models\Notebook;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePageTest extends TestCase
{
    use RefreshDatabase;

    private function enableAiTools(Subject $subject): void
    {
        $subject->features()
            ->where('feature', SubjectFeature::AiTools->value)
            ->update(['is_enabled' => true]);
        $subject->forgetFeatureCache();
    }

    public function test_teacher_can_open_notebook_workspace(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)
            ->get(route('studio.ai'))
            ->assertOk()
            ->assertSee('Notebook');

        $this->assertDatabaseHas('notebooks', ['owner_id' => $teacher->id, 'subject_id' => $subject->id]);
    }

    public function test_reopening_workspace_reuses_same_notebook(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('studio.ai'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.ai'))->assertOk();

        $this->assertSame(1, Notebook::query()->where('owner_id', $teacher->id)->count());
    }

    public function test_student_cannot_open_workspace(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $student = User::factory()->student($subject)->create();

        $this->actingAs($student)->get(route('studio.ai'))->assertForbidden();
    }

    public function test_workspace_forbidden_when_feature_disabled(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('studio.ai'))->assertForbidden();
    }
}
