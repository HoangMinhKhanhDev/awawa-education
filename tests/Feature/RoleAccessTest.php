<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_teacher_area(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('studio'))->assertForbidden();
    }

    public function test_teacher_can_access_teacher_area(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('studio'))->assertOk();
    }

    public function test_student_cannot_access_admin_area(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.users'))->assertForbidden();
    }

    public function test_teacher_cannot_access_admin_area(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('admin.users'))->assertForbidden();
    }

    public function test_super_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.api-keys'))->assertOk();
        $this->actingAs($admin)->get(route('admin.stats'))->assertOk();
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('dashboard'))->assertOk();
    }
}
