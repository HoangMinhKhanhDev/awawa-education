<?php

namespace Tests\Feature\Admin;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_stats(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.stats'))->assertOk()->assertSee('Thống kê');
    }

    public function test_teacher_cannot_open_stats(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $this->actingAs($teacher)->get(route('admin.stats'))->assertForbidden();
    }
}
