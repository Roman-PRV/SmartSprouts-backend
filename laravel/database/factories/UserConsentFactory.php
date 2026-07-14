<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserConsent>
 */
class UserConsentFactory extends Factory
{
    protected $model = UserConsent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email_hash' => null,
            'type' => UserConsent::TYPE_TERMS,
            'document_version' => config('legal.terms_version'),
            'accepted_at' => now(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Consent row for the Privacy Policy instead of the Terms.
     */
    public function privacy(): static
    {
        return $this->state(fn (): array => [
            'type' => UserConsent::TYPE_PRIVACY,
            'document_version' => config('legal.privacy_version'),
        ]);
    }
}
