<?php

namespace Database\Factories\Entitlement;

use App\Enums\Entitlement\ExemptionReasonEnum;
use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessExemption>
 */
class AccessExemptionFactory extends Factory
{
    protected $model = AccessExemption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => ExemptionReasonEnum::TESTER,
            'note' => fake()->sentence(),
            'granted_by' => User::factory()->admin(),
            'granted_at' => now(),
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (): array => [
            'reason' => ExemptionReasonEnum::STAFF,
        ]);
    }

    /**
     * The grant outliving the admin who made it.
     */
    public function withoutGrantor(): static
    {
        return $this->state(fn (): array => [
            'granted_by' => null,
        ]);
    }
}
