<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'created_by' => User::factory()->teacher(),
            'type' => ExamType::Exam,
            'title' => 'Đề '.fake()->words(2, true),
            'description' => null,
            'status' => ExamStatus::Draft,
            'total_points' => 0,
        ];
    }

    public function assignment(): static
    {
        return $this->state(fn () => ['type' => ExamType::Assignment]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ExamStatus::Published]);
    }
}
