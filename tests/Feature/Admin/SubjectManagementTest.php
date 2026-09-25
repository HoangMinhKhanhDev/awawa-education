<?php

namespace Tests\Feature\Admin;

use App\Enums\SubjectFeature;
use App\Livewire\Admin\Subjects\Index as AdminSubjects;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_subject_with_features(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminSubjects::class)
            ->call('openCreate')
            ->set('name', 'Chuyên đề Số học')
            ->set('code', 'so-hoc')
            ->set('features.'.SubjectFeature::AiTools->value, true)
            ->set('features.'.SubjectFeature::Exams->value, true)
            ->call('save')
            ->assertHasNoErrors();

        $subject = Subject::query()->where('code', 'so-hoc')->firstOrFail();

        $this->assertTrue($subject->hasFeature(SubjectFeature::Exams));
        $this->assertTrue($subject->hasFeature(SubjectFeature::AiTools));
    }

    public function test_subject_cannot_be_deleted_when_it_has_users(): void
    {
        $subject = Subject::factory()->create();
        User::factory()->teacher($subject)->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(AdminSubjects::class)->call('delete', $subject->id);

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_teacher_cannot_manage_subjects(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('admin.subjects'))->assertForbidden();
    }
}
