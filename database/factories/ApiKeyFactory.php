<?php

namespace Database\Factories;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'prefix' => 'awawa_'.fake()->bothify('??????'),
            'key_hash' => hash('sha256', fake()->uuid()),
            'scopes' => ApiScope::values(),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ];
    }
}
