<?php

namespace Database\Factories;

require_once __DIR__ . '/../stubs.php';

use App\Models\GameLevel;
use App\Models\PipeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameLevelFactory extends Factory
{
    protected $model = GameLevel::class;

    public function definition(): array
    {
        $sizes = ['3x8', '3x10', '3x12', '5x4', '5x6', '5x8', '5x10', '5x12'];
        $mapSize = \fake()->randomElement($sizes);
        [$rows, $cols] = array_map('intval', explode('x', $mapSize));
        $title = 'Màn chơi ' . \fake()->words(3, true) . ' (' . $mapSize . ')';

        return [
            'category_id' => 1,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . \fake()->numberBetween(100, 999),
            'map_size' => $mapSize,
            'grid_rows' => $rows,
            'grid_cols' => $cols,
            'difficulty' => \fake()->randomElement(['easy', 'medium', 'hard']),
            'time_limit_seconds' => \fake()->randomElement([45, 50, 60]),
            'reward_stars' => \fake()->numberBetween(100, 350),
            'thumbnail' => 'Images/NenNho.jpg',
            'description' => \fake()->sentence(15),
            'map_config_json' => [
                'yellowProb' => 0.25,
                'redProb' => 0.15,
                'wallProb' => 0.15,
                'crossProb' => 0.10,
            ],
            'is_published' => true,
        ];
    }
}