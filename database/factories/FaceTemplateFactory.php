<?php

namespace Database\Factories;

use App\Models\FaceTemplate;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaceTemplateFactory extends Factory
{
    protected $model = FaceTemplate::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'image_path' => null,
            'embedding' => array_fill(0, 512, 0.5), // Default embedding vector
            'model' => 'test-model',
            'score' => 0.95,
            'source' => 'test',
        ];
    }
}
