<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Đăng nhập');
    }

    public function test_register_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'teacher@awawa.test',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'teacher@awawa.test')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'teacher@awawa.test',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'teacher@awawa.test')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_new_registration_creates_a_student_without_subject(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Nguyễn Văn A')
            ->set('email', 'student@awawa.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'student@awawa.test',
            'role' => Role::Student->value,
            'subject_id' => null,
        ]);
    }

    public function test_registration_requires_matching_password(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Nguyễn Văn A')
            ->set('email', 'student@awawa.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different-password')
            ->set('terms', true)
            ->call('register')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'student@awawa.test']);
    }
}
