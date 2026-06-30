<?php

namespace Database\Factories\Games\TrueFalseImage\Models;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrueFalseImageLevel>
 */
class TrueFalseImageLevelFactory extends Factory
{
    protected $model = TrueFalseImageLevel::class;

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
            'image_url' => TrueFalseImageLevel::STORAGE_ROOT.'/image.jpg',
        ];
    }
}
