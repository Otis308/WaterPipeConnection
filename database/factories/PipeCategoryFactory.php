<?php

namespace Database\Factories;

require_once __DIR__ . '/../stubs.php';

use App\Models\PipeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PipeCategoryFactory extends Factory
{
    protected $model = PipeCategory::class;

    public function definition(): array
    {
        $name = 'Chủ đề Ống Nước ' . \fake()->unique()->words(2, true);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => \fake()->sentence(12),
            'score_multiplier' => \fake()->randomElement([1.0, 1.25, 1.5, 2.0]),
        ];
    }
}