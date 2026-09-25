<?php

namespace Database\Seeders;

use App\Enums\SubjectFeature;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('awawa.default_subjects', []) as $index => $data) {
            $subject = Subject::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'color' => $data['color'] ?? '#2563EB',
                    'is_active' => true,
                    'order' => $index,
                ],
            );

            foreach (SubjectFeature::cases() as $feature) {
                $subject->features()->updateOrCreate(
                    ['feature' => $feature->value],
                    ['is_enabled' => $feature->defaultEnabled()],
                );
            }
        }
    }
}
