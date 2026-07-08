<?php

namespace Database\Factories\Games\TrueFalseImage\Models;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrueFalseImageStatement>
 */
class TrueFalseImageStatementFactory extends Factory
{
    protected $model = TrueFalseImageStatement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => TrueFalseImageLevel::factory(),
            'statement' => [
                'uk' => $this->faker->sentence(),
                'en' => $this->faker->sentence(),
                'es' => $this->faker->sentence(),
            ],
            'explanation' => [
                'uk' => $this->faker->sentence(),
                'en' => $this->faker->sentence(),
                'es' => $this->faker->sentence(),
            ],
            'is_true' => $this->faker->boolean(),
        ];
    }
}
