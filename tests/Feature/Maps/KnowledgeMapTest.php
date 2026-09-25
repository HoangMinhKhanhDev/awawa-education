<?php

namespace Tests\Feature\Maps;

use App\Enums\MapVisibility;
use App\Enums\SubjectFeature;
use App\Livewire\Maps\Editor;
use App\Livewire\Maps\Index;
use App\Models\KnowledgeMap;
use App\Models\KnowledgeMapVersion;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeMapTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::factory()->create();
        $this->student = User::factory()->student($this->subject)->create();

        $this->actingAs($this->student);
        app(SubjectContext::class)->set($this->subject->id);
    }

    private function scene(array $elements = []): string
    {
        return json_encode([
            'type' => 'excalidraw',
            'elements' => $elements,
            'appState' => ['viewBackgroundColor' => '#ffffff'],
            'files' => [],
        ]);
    }

    public function test_user_can_create_map_with_initial_version(): void
    {
        Livewire::test(Index::class)
            ->call('openCreate')
            ->set('title', 'Sơ đồ chuyên đề 1')
            ->call('save')
            ->assertHasNoErrors();

        $map = KnowledgeMap::query()->where('title', 'Sơ đồ chuyên đề 1')->firstOrFail();

        $this->assertSame($this->subject->id, $map->subject_id);
        $this->assertSame($this->student->id, $map->owner_id);
        $this->assertSame(1, $map->current_version);
        $this->assertDatabaseHas('knowledge_map_versions', [
            'knowledge_map_id' => $map->id,
            'version' => 1,
        ]);
    }

    public function test_owner_can_save_new_version(): void
    {
        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
            'current_version' => 1,
        ]);

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => 1,
            'scene' => json_decode($this->scene(), true),
        ]);

        Livewire::test(Editor::class, ['map' => $map])
            ->set('scene', $this->scene([['id' => 'n1', 'type' => 'rectangle']]))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $map->fresh()->current_version);
        $this->assertSame(2, $map->versions()->count());
    }

    public function test_saving_invalid_scene_fails_validation(): void
    {
        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
        ]);

        Livewire::test(Editor::class, ['map' => $map])
            ->set('scene', 'not-json')
            ->call('save')
            ->assertHasErrors('scene');

        $this->assertSame(0, $map->fresh()->current_version);
    }

    public function test_restore_creates_new_version_from_old_scene(): void
    {
        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
            'current_version' => 2,
        ]);

        $v1 = KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => 1,
            'scene' => json_decode($this->scene([['id' => 'old']]), true),
        ]);

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => 2,
            'scene' => json_decode($this->scene([['id' => 'new']]), true),
        ]);

        Livewire::test(Editor::class, ['map' => $map])->call('restore', $v1->id);

        $map->refresh();

        $this->assertSame(3, $map->current_version);
        $latest = $map->latestVersion;
        $this->assertSame('old', $latest->scene['elements'][0]['id']);
    }

    public function test_student_cannot_edit_others_private_map(): void
    {
        $other = User::factory()->student($this->subject)->create();

        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $other->id,
            'visibility' => MapVisibility::Private,
        ]);

        Livewire::test(Editor::class, ['map' => $map])->assertForbidden();
    }

    public function test_shared_visible_map_appears_in_list_but_private_does_not(): void
    {
        $other = User::factory()->student($this->subject)->create();

        $shared = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $other->id,
            'visibility' => MapVisibility::Subject,
            'title' => 'Sơ đồ chia sẻ',
        ]);

        $private = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $other->id,
            'visibility' => MapVisibility::Private,
            'title' => 'Sơ đồ riêng tư',
        ]);

        Livewire::test(Index::class)
            ->assertSee('Sơ đồ chia sẻ')
            ->assertDontSee('Sơ đồ riêng tư');

        $this->assertNotNull($shared);
        $this->assertNotNull($private);
    }

    public function test_sharing_enables_public_link_page(): void
    {
        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
        ]);

        Livewire::test(Index::class)->call('share', $map->id);

        $map->refresh();

        $this->assertSame(MapVisibility::Link, $map->visibility);
        $this->assertNotNull($map->share_token);

        $this->get(route('maps.shared', $map->share_token))->assertOk();
    }

    public function test_shared_page_not_found_for_invalid_token(): void
    {
        $this->get(route('maps.shared', 'khong-ton-tai'))->assertNotFound();
    }

    public function test_private_map_token_is_not_publicly_accessible(): void
    {
        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
            'visibility' => MapVisibility::Private,
        ]);

        $token = $map->ensureShareToken();

        $this->get(route('maps.shared', $token))->assertNotFound();
    }

    public function test_feature_gate_blocks_map_when_disabled(): void
    {
        $this->subject->features()
            ->where('feature', SubjectFeature::KnowledgeMap->value)
            ->update(['is_enabled' => false]);

        $this->get(route('map'))->assertForbidden();
    }

    public function test_map_pages_render_over_http(): void
    {
        $this->get(route('map'))->assertOk();

        $map = KnowledgeMap::factory()->create([
            'subject_id' => $this->subject->id,
            'owner_id' => $this->student->id,
        ]);

        $this->get(route('maps.edit', $map))->assertOk();
    }
}
