<?php

namespace Database\Factories\Games\TrueFalseText\Models;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrueFalseTextLevel>
 */
class TrueFalseTextLevelFactory extends Factory
{
    protected $model = TrueFalseTextLevel::class;

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
            'text' => [
                'uk' => $this->faker->paragraph(),
                'en' => $this->faker->paragraph(),
                'es' => $this->faker->paragraph(),
            ],
        ];
    }
}
