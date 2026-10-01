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

    public function test_teacher_sees_notebook_index_without_auto_creating(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)
            ->get(route('studio.ai'))
            ->assertOk()
            ->assertSee('Sổ tay AI')
            ->assertSee('Chưa có sổ tay nào');

        $this->assertSame(0, Notebook::query()->where('owner_id', $teacher->id)->count());
    }

    public function test_teacher_can_open_notebook_workspace(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id, 'owner_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get(route('studio.ai.notebook', ['notebookId' => $notebook->id]))
            ->assertOk()
            ->assertSee(e($notebook->title), escape: false);
    }

    public function test_workspace_exposes_resizable_panels_with_keyboard_and_double_click_reset(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id, 'owner_id' => $teacher->id]);

        $html = $this->actingAs($teacher)->get(route('studio.ai.notebook', ['notebookId' => $notebook->id]))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="{ ...awawaNotebookPanels(), mobileTab:', $html);
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

    /**
     * Tab mobile phải chuyển tức thì phía client: hàng đợi Livewire có thể nghẽn
     * sau request AI dài, bấm tab lúc đó vẫn phải chuyển màn hình ngay.
     */
    public function test_mobile_tabs_switch_instantly_without_waiting_for_the_server(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id, 'owner_id' => $teacher->id]);

        $html = $this->actingAs($teacher)->get(route('studio.ai.notebook', ['notebookId' => $notebook->id]))->assertOk()->getContent();

        foreach (['sources', 'chat', 'studio'] as $tab) {
            $this->assertStringContainsString("@click=\"mobileTab = '{$tab}'\"", $html);
            $this->assertStringContainsString(":class=\"mobileTab === '{$tab}' ? 'flex' : 'hidden'\"", $html);
        }

        $this->assertSame(3, substr_count($html, 'x-cloak :class="mobileTab'));
    }

    /**
     * Chuỗi chiều cao mobile: wrapper phải có chiều cao cố định để khung chat bên
     * trong cuộn được, thay vì phình theo nội dung rồi bị cắt ở h-dvh.
     */
    public function test_fullbleed_layout_constrains_height_for_inner_scrolling(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id, 'owner_id' => $teacher->id]);

        $html = $this->actingAs($teacher)->get(route('studio.ai.notebook', ['notebookId' => $notebook->id]))->assertOk()->getContent();

        $this->assertStringContainsString(
            '<div class="flex min-w-0 flex-1 flex-col h-full min-h-0">',
            $html,
        );
    }

    public function test_reopening_index_does_not_create_notebooks(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('studio.ai'))->assertOk();
        $this->actingAs($teacher)->get(route('studio.ai'))->assertOk();

        $this->assertSame(0, Notebook::query()->where('owner_id', $teacher->id)->count());
    }

    public function test_workspace_top_bar_links_back_to_index(): void
    {
        $subject = Subject::factory()->create();
        $this->enableAiTools($subject);
        $teacher = User::factory()->teacher($subject)->create();
        $notebook = Notebook::factory()->create(['subject_id' => $subject->id, 'owner_id' => $teacher->id]);

        $html = $this->actingAs($teacher)->get(route('studio.ai.notebook', ['notebookId' => $notebook->id]))->assertOk()->getContent();

        $this->assertStringContainsString(route('studio.ai'), $html);
        $this->assertStringContainsString(e($notebook->title), $html);
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
            'purpose' => 'self_chat_visible',
            'total_tokens' => 100,
            'is_success' => true,
        ]);
        AiUsageLog::create([
            'subject_id' => $subject->id,
            'user_id' => $otherTeacher->id,
            'provider_key' => 'openrouter',
            'model' => 'other-model-hidden',
            'purpose' => 'other_chat_hidden',
            'total_tokens' => 100,
            'is_success' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('studio.ai.activity'))
            ->assertSee('Self Chat Visible')
            ->assertDontSee('Other Chat Hidden')
            ->assertDontSee('other-model-hidden');
    }
}
