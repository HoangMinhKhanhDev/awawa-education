<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignable_type' => Exam::class,
            'assignable_id' => Exam::factory(),
            'assigned_by' => null,
            'assigned_at' => now(),
        ];
    }

    public function recalled(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'recalled_at' => now(),
            'recall_reason' => $reason,
        ]);
    }

    public function dueOn(\DateTimeInterface|string $when): static
    {
        return $this->state(fn (): array => ['due_at' => $when]);
    }

    public function assignedBy(User $teacher): static
    {
        return $this->state(fn (): array => ['assigned_by' => $teacher->id]);
    }
}
