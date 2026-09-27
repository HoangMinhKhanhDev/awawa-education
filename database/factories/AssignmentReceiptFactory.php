<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentReceipt>
 */
class AssignmentReceiptFactory extends Factory
{
    protected $model = AssignmentReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory()->student(),
            'delivered_at' => now(),
        ];
    }

    public function opened(): static
    {
        return $this->state(fn (): array => ['opened_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'opened_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function untouched(): static
    {
        return $this->state(fn (): array => ['delivered_at' => null]);
    }
}
