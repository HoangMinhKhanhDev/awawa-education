<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'created_by' => User::factory()->teacher(),
            'type' => QuestionType::MultipleChoice,
            'content' => fake()->sentence().'?',
            'answer' => null,
            'explanation' => null,
            'difficulty' => Difficulty::Medium,
            'points' => 1,
            'topic' => fake()->word(),
            'is_active' => true,
        ];
    }

    public function essay(): static
    {
        return $this->state(fn () => ['type' => QuestionType::Essay]);
    }

    public function fillBlank(): static
    {
        return $this->state(fn () => ['type' => QuestionType::FillBlank, 'answer' => fake()->word()]);
    }
}
