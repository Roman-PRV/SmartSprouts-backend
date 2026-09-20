<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Every account this factory creates accepts the documents in force, as
 * registration does — so consent rows exist without a test asking for them.
 * An account carrying none is the exception (a Google signup, an account
 * predating the gate, one facing a version it has not answered), and tests
 * that want it say withoutConsent().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Write the consent rows described above after the account row lands.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $versions = [
                UserConsent::TYPE_TERMS => config('legal.terms_version'),
                UserConsent::TYPE_PRIVACY => config('legal.privacy_version'),
            ];

            foreach ($versions as $type => $version) {
                $user->consents()->create([
                    'type' => $type,
                    'document_version' => $version,
                    'accepted_at' => now(),
                ]);
            }
        });
    }

    /**
     * An account with nothing on record for the current documents.
     *
     * Removes what configure() wrote instead of skipping it: after-creating
     * callbacks are carried into every derived factory instance and there is
     * no way to unregister one.
     *
     * @return $this
     */
    public function withoutConsent(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->consents()->delete();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return $this
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has admin privileges.
     *
     * @return $this
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
