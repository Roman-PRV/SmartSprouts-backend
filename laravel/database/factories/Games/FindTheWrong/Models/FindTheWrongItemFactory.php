<?php

namespace Database\Factories\Games\FindTheWrong\Models;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FindTheWrongItem>
 */
class FindTheWrongItemFactory extends Factory
{
    protected $model = FindTheWrongItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $x = $this->faker->randomFloat(2, 0.05, 0.4);
        $y = $this->faker->randomFloat(2, 0.05, 0.4);
        $w = $this->faker->randomFloat(2, 0.1, 0.3);
        $h = $this->faker->randomFloat(2, 0.1, 0.3);

        return [
            'level_id' => FindTheWrongLevel::factory(),
            'polygon' => [
                [$x, $y],
                [$x + $w, $y],
                [$x + $w, $y + $h],
                [$x, $y + $h],
            ],
            'name' => [
                'uk' => $this->faker->words(3, true),
                'en' => $this->faker->words(3, true),
                'es' => $this->faker->words(3, true),
            ],
            'name_audio_url' => [],
            'explanation' => [
                'uk' => $this->faker->sentence(),
                'en' => $this->faker->sentence(),
                'es' => $this->faker->sentence(),
            ],
            'explanation_audio_url' => [],
        ];
    }
}
