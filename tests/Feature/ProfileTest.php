<?php

namespace Tests\Feature;

use App\Livewire\Profile\Show as ProfileShow;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_profile(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create(['name' => 'Tên cũ']);

        $this->actingAs($student);

        Livewire::test(ProfileShow::class)
            ->set('name', 'Tên mới')
            ->set('phone', '0912345678')
            ->set('className', '11A1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Tên mới', $student->fresh()->name);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'phone' => '0912345678',
            'class_name' => '11A1',
        ]);
    }

    public function test_profile_page_renders(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('profile'))->assertOk();
    }

    public function test_admin_can_open_profile_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('profile'))->assertOk();
    }
}
