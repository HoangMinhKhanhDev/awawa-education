<?php

namespace Database\Factories;

use App\Enums\SubjectFeature;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'code' => $code,
            'name' => Str::headline(str_replace('-', ' ', $code)),
            'color' => '#2563EB',
            'description' => null,
            'is_active' => true,
            'order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Subject $subject): void {
            if ($subject->features()->exists()) {
                return;
            }

            foreach (SubjectFeature::cases() as $feature) {
                $subject->features()->create([
                    'feature' => $feature->value,
                    'is_enabled' => $feature->defaultEnabled(),
                ]);
            }
        });
    }
}
