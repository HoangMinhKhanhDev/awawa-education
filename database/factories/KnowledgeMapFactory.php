<?php

namespace Database\Factories;

use App\Enums\MapVisibility;
use App\Models\KnowledgeMap;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeMap>
 */
class KnowledgeMapFactory extends Factory
{
    protected $model = KnowledgeMap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'owner_id' => User::factory()->student(),
            'title' => fake()->sentence(3),
            'visibility' => MapVisibility::Private,
            'current_version' => 0,
        ];
    }
}
