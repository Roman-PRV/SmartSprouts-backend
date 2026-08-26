<?php

namespace Database\Factories\Entitlement;

use App\Models\Entitlement\LevelDailyUsage;
use App\Models\Game;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LevelDailyUsage>
 */
class LevelDailyUsageFactory extends Factory
{
    protected $model = LevelDailyUsage::class;

    /**
     * Define the model's default state.
     *
     * A level started today and not finished.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // One reading of the clock for both fields: two would let the date and
        // the time land on opposite sides of midnight.
        $openedAt = now();

        return [
            'user_id' => User::factory(),
            'usage_date' => $openedAt->toDateString(),
            'game_id' => Game::factory(),
            'level_id' => fake()->numberBetween(1, 20),
            'opened_at' => $openedAt,
            'completed_at' => null,
        ];
    }

    /**
     * A level that was also finished, so it counts against both allowances.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => Carbon::parse($attributes['opened_at'])->addMinutes(5),
        ]);
    }

    /**
     * Usage recorded on an earlier day — what the pruning command and
     * "yesterday does not count against today" both need.
     *
     * Completion is moved onto that day too, so the two states chain in either
     * order. Left alone it would stay on today's clock and describe a level
     * finished months after it was opened, which nothing can produce.
     */
    public function onDay(DateTimeInterface $date): static
    {
        return $this->state(function (array $attributes) use ($date): array {
            $opened = Carbon::parse($date)->setTime(12, 0);

            return [
                'usage_date' => $opened->toDateString(),
                'opened_at' => $opened,
                'completed_at' => $attributes['completed_at'] === null
                    ? null
                    : $opened->copy()->addMinutes(5),
            ];
        });
    }
}
