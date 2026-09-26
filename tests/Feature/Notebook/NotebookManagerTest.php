<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Manager;
use App\Models\Notebook;
use App\Models\NotebookArtifact;
use App\Models\NotebookMessage;
use App\Models\NotebookSource;
use App\Models\Subject;
use App\Models\User;
use App\Support\NotebookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotebookManagerTest extends TestCase
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
    }

    public function test_a_teacher_can_create_several_notebooks(): void
    {
        $first = Notebook::defaultFor($this->teacher);

        $this->assertSame(1, Notebook::forUser($this->teacher)->count());

        Livewire::test(Manager::class, ['notebookId' => $first->id])
            ->set('newTitle', 'Chuyên đề bất đẳng thức')
            ->call('create')
            ->assertHasNoErrors();

        $notebooks = Notebook::forUser($this->teacher);

        $this->assertCount(2, $notebooks);
        $this->assertSame('Chuyên đề bất đẳng thức', $notebooks->last()->title);
        $this->assertSame($this->teacher->id, $notebooks->last()->owner_id);
        $this->assertSame($this->subject->id, $notebooks->last()->subject_id);
    }

    public function test_a_notebook_without_a_title_gets_a_generated_name(): void
    {
        $first = Notebook::defaultFor($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $first->id])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame('Notebook 2', Notebook::forUser($this->teacher)->last()->title);
    }

    public function test_creating_is_blocked_at_the_configured_limit(): void
    {
        config()->set('awawa.notebook.max_notebooks', 1);

        $notebook = Notebook::defaultFor($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->set('newTitle', 'Vượt giới hạn')
            ->call('create')
            ->assertSet('error', 'Bạn đã đạt giới hạn 1 notebook.');

        $this->assertSame(1, Notebook::query()->where('owner_id', $this->teacher->id)->count());
        $this->assertSame(1, NotebookConfig::maxNotebooksPerUser());
    }

    public function test_creating_is_blocked_when_the_limit_is_reached(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->call('create')
            ->assertSet('error', null)
            ->assertSet('atLimit', false);

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->set('newTitle', 'Không được tạo')
            ->call('create')
            ->assertSet('error', null)
            ->assertSet('atLimit', false)
            ->assertSee('Tạo notebook');
    }

    public function test_a_notebook_can_be_renamed(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->call('rename', $notebook->id, '  Ôn tập quang học  ')
            ->assertSet('error', null);

        $this->assertSame('Ôn tập quang học', $notebook->fresh()->title);
    }

    public function test_renaming_rejects_a_blank_title(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);
        $title = $notebook->title;

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->call('rename', $notebook->id, '   ')
            ->assertSet('error', 'Tên notebook không được để trống.');

        $this->assertSame($title, $notebook->fresh()->title);
    }

    public function test_deleting_a_notebook_removes_its_content(): void
    {
        $keep = Notebook::defaultFor($this->teacher);
        $remove = Notebook::createFor($this->teacher, 'Tạm');

        $source = new NotebookSource(['notebook_id' => $remove->id, 'title' => 'Nguồn', 'type' => 'text', 'status' => 'ready']);
        $source->save();

        NotebookMessage::create(['notebook_id' => $remove->id, 'role' => 'user', 'content' => 'Hỏi']);
        NotebookArtifact::create([
            'notebook_id' => $remove->id,
            'subject_id' => $this->subject->id,
            'user_id' => $this->teacher->id,
            'type' => 'document',
            'title' => 'Bản nháp',
            'status' => 'draft',
        ]);

        Livewire::test(Manager::class, ['notebookId' => $keep->id])
            ->call('delete', $remove->id)
            ->assertSet('error', null);

        $this->assertDatabaseMissing('notebooks', ['id' => $remove->id]);
        $this->assertDatabaseMissing('notebook_sources', ['id' => $source->id]);
        $this->assertDatabaseCount('notebook_messages', 0);
        $this->assertDatabaseCount('notebook_artifacts', 0);
    }

    public function test_the_last_notebook_cannot_be_deleted(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $notebook->id])
            ->call('delete', $notebook->id)
            ->assertSet('error', 'Không thể xoá notebook cuối cùng. Hãy tạo notebook khác trước.');

        $this->assertDatabaseHas('notebooks', ['id' => $notebook->id]);
    }

    public function test_a_teacher_cannot_manage_another_teachers_notebook(): void
    {
        $this->subject->features()->update(['is_enabled' => true]);
        $this->subject->forgetFeatureCache();

        $other = User::factory()->teacher($this->subject)->create();
        $theirs = Notebook::defaultFor($other);

        $this->actingAs($other)
            ->get(route('studio.ai.notebook', ['notebookId' => $theirs->id]))
            ->assertOk();

        $this->actingAs($this->teacher);

        Livewire::test(Manager::class, ['notebookId' => $theirs->id])->assertForbidden();
    }

    public function test_deleting_another_teachers_notebook_is_forbidden(): void
    {
        $mine = Notebook::defaultFor($this->teacher);

        $other = User::factory()->teacher($this->subject)->create();
        $theirs = Notebook::defaultFor($other);

        Livewire::test(Manager::class, ['notebookId' => $mine->id])
            ->call('delete', $theirs->id)
            ->assertForbidden();

        $this->assertDatabaseHas('notebooks', ['id' => $theirs->id]);
    }

    public function test_workspace_opens_the_requested_notebook_with_its_own_content(): void
    {
        $this->subject->features()->update(['is_enabled' => true]);
        $this->subject->forgetFeatureCache();

        $default = Notebook::defaultFor($this->teacher);
        $other = Notebook::createFor($this->teacher, 'Chuyên đề riêng');

        NotebookSource::create([
            'notebook_id' => $other->id,
            'title' => 'Nguồn riêng',
            'type' => 'text',
            'status' => 'ready',
        ]);

        NotebookSource::create([
            'notebook_id' => $default->id,
            'title' => 'Nguồn mặc định',
            'type' => 'text',
            'status' => 'ready',
        ]);

        $this->get(route('studio.ai'))
            ->assertOk()
            ->assertSee('Nguồn mặc định')
            ->assertDontSee('Nguồn riêng');

        $this->get(route('studio.ai.notebook', ['notebookId' => $other->id]))
            ->assertOk()
            ->assertSee('Nguồn riêng')
            ->assertDontSee('Nguồn mặc định');
    }

    public function test_workspace_rejects_a_notebook_of_another_teacher(): void
    {
        $this->subject->features()->update(['is_enabled' => true]);
        $this->subject->forgetFeatureCache();

        $other = User::factory()->teacher($this->subject)->create();
        $theirs = Notebook::defaultFor($other);

        $this->get(route('studio.ai.notebook', ['notebookId' => $theirs->id]))->assertNotFound();
    }

    public function test_manager_lists_only_the_notebooks_of_the_signed_in_teacher(): void
    {
        $mine = Notebook::defaultFor($this->teacher);

        $other = User::factory()->teacher($this->subject)->create();
        Notebook::createFor($other, 'Notebook của người khác');

        Livewire::test(Manager::class, ['notebookId' => $mine->id])
            ->assertSee($mine->title)
            ->assertDontSee('Notebook của người khác');
    }
}
