<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'exam_id' => Exam::factory(),
            'student_id' => User::factory()->student(),
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'max_score' => 10,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Graded,
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);
    }
}
