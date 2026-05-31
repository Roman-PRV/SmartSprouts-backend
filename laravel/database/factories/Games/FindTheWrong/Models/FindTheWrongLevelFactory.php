<?php

namespace Database\Factories\Games\FindTheWrong\Models;

use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FindTheWrongLevel>
 */
class FindTheWrongLevelFactory extends Factory
{
    protected $model = FindTheWrongLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => [
                'uk' => $this->faker->sentence(3),
                'en' => $this->faker->sentence(3),
                'es' => $this->faker->sentence(3),
            ],
            'image_url' => FindTheWrongLevel::STORAGE_ROOT.'/image.jpg',
        ];
    }
}
