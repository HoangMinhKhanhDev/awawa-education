<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'created_by' => User::factory()->teacher(),
            'title' => fake()->sentence(3),
            'category' => 'Chuyên đề',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_name' => 'tai-lieu.pdf',
            'mime' => 'application/pdf',
            'size' => 102400,
            'is_public' => true,
        ];
    }
}
