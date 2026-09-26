<?php

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notebook>
 */
class NotebookFactory extends Factory
{
    protected $model = Notebook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'owner_id' => User::factory()->teacher(),
            'title' => 'Notebook '.fake()->word(),
        ];
    }
}
