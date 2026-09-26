<?php

namespace Tests\Feature\Notebook;

use App\Enums\SubjectFeature;
use App\Models\AiUsageLog;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
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

    public function test_workspace_exposes_resizable_panels_with_keyboard_and_double_click_reset(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();

        $html = $this->actingAs($teacher)->get(route('studio.ai'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="awawaNotebookPanels()"', $html);
        $this->assertStringContainsString('class="notebook-panes', $html);
        $this->assertStringContainsString('--nb-sources: ${sourcesWidth}px; --nb-studio: ${studioWidth}px', $html);
        $this->assertSame(2, substr_count($html, 'lg:w-[var(--nb-sources)]') + substr_count($html, 'lg:w-[var(--nb-studio)]'));
        $this->assertSame(2, substr_count($html, 'role="separator"'));
        $this->assertSame(2, substr_count($html, 'class="resize-handle'));
        $this->assertSame(2, substr_count($html, '@dblclick="reset()"'));
        $this->assertSame(2, substr_count($html, '@keydown.left.prevent'));
        $this->assertSame(2, substr_count($html, '@keydown.right.prevent'));
    }

    public function test_panel_resizing_is_defined_in_the_javascript_bundle(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('window.awawaNotebookPanels', $script);
        $this->assertStringContainsString('awawa.notebook.panels.v1', $script);
        $this->assertStringContainsString('(min-width: 1024px)', $script);
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

    public function test_teacher_can_download_docx_for_their_own_artifact(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::defaultFor($teacher);
        $artifact = NotebookArtifact::create([
            'notebook_id' => $notebook->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'type' => 'document',
            'title' => 'Bất đẳng thức Cauchy',
            'text_content' => "# Định lý\nNội dung tài liệu.",
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->get(route('studio.ai.artifacts.export', ['artifact' => $artifact->id, 'format' => 'docx']))
            ->assertDownload('bat-dang-thuc-cauchy.docx');
    }

    public function test_teacher_cannot_download_another_teachers_artifact(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $owner = User::factory()->teacher($subject)->create();
        $otherTeacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::defaultFor($owner);
        $artifact = NotebookArtifact::create([
            'notebook_id' => $notebook->id,
            'subject_id' => $subject->id,
            'user_id' => $owner->id,
            'type' => 'document',
            'title' => 'Private artifact',
            'text_content' => 'Private content.',
            'status' => 'draft',
        ]);

        $this->actingAs($otherTeacher)
            ->get(route('studio.ai.artifacts.export', ['artifact' => $artifact->id, 'format' => 'docx']))
            ->assertNotFound();
    }

    public function test_teacher_activity_shows_only_their_own_ai_usage(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $otherTeacher = User::factory()->teacher($subject)->create();
        AiUsageLog::create([
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'provider_key' => 'openrouter',
            'model' => 'self-model-visible',
            'purpose' => 'chat',
            'total_tokens' => 100,
            'is_success' => true,
        ]);
        AiUsageLog::create([
            'subject_id' => $subject->id,
            'user_id' => $otherTeacher->id,
            'provider_key' => 'openrouter',
            'model' => 'other-model-hidden',
            'purpose' => 'chat',
            'total_tokens' => 100,
            'is_success' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('studio.ai.activity'))
            ->assertSee('self-model-visible')
            ->assertDontSee('other-model-hidden');
    }
}
