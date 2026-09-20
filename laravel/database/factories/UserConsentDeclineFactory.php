<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserConsentDecline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserConsentDecline>
 */
class UserConsentDeclineFactory extends Factory
{
    protected $model = UserConsentDecline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => UserConsent::TYPE_TERMS,
            'document_version' => config('legal.terms_version'),
            'declined_at' => now(),
        ];
    }

    /**
     * Decline row for the Privacy Policy instead of the Terms.
     */
    public function privacy(): static
    {
        return $this->state(fn (): array => [
            'type' => UserConsent::TYPE_PRIVACY,
            'document_version' => config('legal.privacy_version'),
        ]);
    }
}
