<?php

namespace Database\Factories\Games\TrueFalseText\Models;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrueFalseTextStatement>
 */
class TrueFalseTextStatementFactory extends Factory
{
    protected $model = TrueFalseTextStatement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => TrueFalseTextLevel::factory(),
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
