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
            'title_audio_url' => [],
            'image_url' => 'games/find-the-wrong/levels/image.jpg',
        ];
    }
}
