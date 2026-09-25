<?php

namespace Tests\Feature\Admin;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index as AdminUsers;
use App\Livewire\Auth\Login;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_teacher_with_subject(): void
    {
        $subject = Subject::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(AdminUsers::class)
            ->call('openCreate')
            ->set('name', 'Giáo viên A')
            ->set('email', 'gv.a@awawa.test')
            ->set('role', Role::Teacher->value)
            ->set('subjectId', $subject->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'gv.a@awawa.test',
            'role' => Role::Teacher->value,
            'subject_id' => $subject->id,
        ]);

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => User::query()->where('email', 'gv.a@awawa.test')->value('id'),
        ]);
    }

    public function test_teacher_requires_a_subject(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminUsers::class)
            ->call('openCreate')
            ->set('name', 'Giáo viên B')
            ->set('email', 'gv.b@awawa.test')
            ->set('role', Role::Teacher->value)
            ->set('subjectId', null)
            ->call('save')
            ->assertHasErrors('subjectId');

        $this->assertDatabaseMissing('users', ['email' => 'gv.b@awawa.test']);
    }

    public function test_admin_can_create_guest_student_without_subject(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminUsers::class)
            ->call('openCreate')
            ->set('name', 'Học sinh C')
            ->set('email', 'hs.c@awawa.test')
            ->set('role', Role::Student->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'hs.c@awawa.test',
            'role' => Role::Student->value,
            'subject_id' => null,
        ]);
    }

    public function test_admin_can_add_and_remove_student_from_team(): void
    {
        $subject = Subject::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $student = User::factory()->student($subject)->create();

        $this->actingAs($admin);

        Livewire::test(AdminUsers::class)->call('toggleTeam', $student->id);

        $this->assertDatabaseHas('team_memberships', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => MembershipStatus::Active->value,
        ]);

        Livewire::test(AdminUsers::class)->call('toggleTeam', $student->id);

        $this->assertSame(
            MembershipStatus::Removed,
            TeamMembership::query()->where('student_id', $student->id)->first()->status,
        );
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminUsers::class)->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'locked@awawa.test',
            'password' => 'password123',
            'is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'locked@awawa.test')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
