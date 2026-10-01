<?php

namespace Tests\Feature\Notebook;

use App\Livewire\Notebook\Index;
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

class IndexTest extends TestCase
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

        Livewire::test(Index::class)
            ->set('newTitle', 'Chuyên đề bất đẳng thức')
            ->call('create')
            ->assertHasNoErrors();

        $notebooks = Notebook::forUser($this->teacher);

        $this->assertCount(2, $notebooks);
        $this->assertSame('Chuyên đề bất đẳng thức', $notebooks->last()->title);
        $this->assertSame($this->teacher->id, $notebooks->last()->owner_id);
        $this->assertSame($this->subject->id, $notebooks->last()->subject_id);
        $this->assertNotSame($first->id, $notebooks->last()->id);
    }

    public function test_a_notebook_without_a_title_gets_a_generated_name(): void
    {
        Notebook::defaultFor($this->teacher);

        Livewire::test(Index::class)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame('Notebook 2', Notebook::forUser($this->teacher)->last()->title);
    }

    public function test_creating_is_blocked_at_the_configured_limit(): void
    {
        config()->set('awawa.notebook.max_notebooks', 1);

        Notebook::defaultFor($this->teacher);

        Livewire::test(Index::class)
            ->set('newTitle', 'Vượt giới hạn')
            ->call('create')
            ->assertSet('error', 'Bạn đã đạt giới hạn 1 notebook.');

        $this->assertSame(1, Notebook::query()->where('owner_id', $this->teacher->id)->count());
        $this->assertSame(1, NotebookConfig::maxNotebooksPerUser());
    }

    public function test_index_shows_resume_button_for_the_latest_notebook(): void
    {
        $first = Notebook::defaultFor($this->teacher);
        $second = Notebook::createFor($this->teacher, 'Chuyên đề riêng');
        $second->update(['updated_at' => now()->addMinute()]);

        Livewire::test(Index::class)
            ->assertSee('Tiếp tục làm việc')
            ->assertSee('Chuyên đề riêng')
            ->assertSee($first->title);
    }

    public function test_a_notebook_can_be_renamed(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);

        Livewire::test(Index::class)
            ->call('startRename', $notebook->id)
            ->set('renamingTitle', '  Ôn tập quang học  ')
            ->call('saveRename')
            ->assertSet('renamingId', null);

        $this->assertSame('Ôn tập quang học', $notebook->fresh()->title);
    }

    public function test_renaming_rejects_a_blank_title(): void
    {
        $notebook = Notebook::defaultFor($this->teacher);
        $title = $notebook->title;

        Livewire::test(Index::class)
            ->call('startRename', $notebook->id)
            ->set('renamingTitle', '   ')
            ->call('saveRename')
            ->assertHasErrors('renamingTitle');

        $this->assertSame($title, $notebook->fresh()->title);
    }

    public function test_deleting_a_notebook_removes_its_content(): void
    {
        Notebook::defaultFor($this->teacher);
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

        Livewire::test(Index::class)
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

        Livewire::test(Index::class)
            ->call('delete', $notebook->id)
            ->assertSet('error', 'Không thể xoá notebook cuối cùng. Hãy tạo notebook khác trước.');

        $this->assertDatabaseHas('notebooks', ['id' => $notebook->id]);
    }

    public function test_a_teacher_cannot_manage_another_teachers_notebook(): void
    {
        $mine = Notebook::defaultFor($this->teacher);

        $other = User::factory()->teacher($this->subject)->create();
        $theirs = Notebook::defaultFor($other);

        Livewire::test(Index::class)
            ->call('delete', $theirs->id)
            ->assertForbidden();

        $this->assertDatabaseHas('notebooks', ['id' => $theirs->id]);
        $this->assertDatabaseHas('notebooks', ['id' => $mine->id]);
    }

    public function test_index_lists_only_the_notebooks_of_the_signed_in_teacher(): void
    {
        $mine = Notebook::defaultFor($this->teacher);

        $other = User::factory()->teacher($this->subject)->create();
        Notebook::createFor($other, 'Notebook của người khác');

        Livewire::test(Index::class)
            ->assertSee($mine->title)
            ->assertDontSee('Notebook của người khác');
    }

    public function test_student_cannot_open_the_index(): void
    {
        $student = User::factory()->student($this->subject)->create();
        $this->actingAs($student);

        Livewire::test(Index::class)->assertForbidden();
        $this->get(route('studio.ai'))->assertForbidden();
    }
}
