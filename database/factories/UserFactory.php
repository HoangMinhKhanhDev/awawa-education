<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Student,
            'subject_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::SuperAdmin,
            'subject_id' => null,
        ]);
    }

    public function teacher(?Subject $subject = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Teacher,
            'subject_id' => $subject?->getKey() ?? Subject::factory(),
        ]);
    }

    public function student(?Subject $subject = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Student,
            'subject_id' => $subject?->getKey(),
        ]);
    }
}
