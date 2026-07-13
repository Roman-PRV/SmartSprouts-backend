<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConsent;
use App\Services\ConsentService;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registration captures consent ───────────────────────────────────────

    /** @test */
    public function registration_fails_without_the_consent_checkbox(): void
    {
        $this->withMiddleware()->postJson('/api/auth/register', [
            'name' => 'No Consent',
            'email' => 'no-consent@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    /** @test */
    public function registration_writes_terms_and_privacy_consent_rows_with_current_versions(): void
    {
        config([
            'legal.terms_version' => '2030-01-01',
            'legal.privacy_version' => '2030-02-02',
        ]);

        $this->withMiddleware()
            ->withHeaders(['User-Agent' => 'ConsentTestBrowser/1.0'])
            ->postJson('/api/auth/register', [
                'name' => 'Consenting Parent',
                'email' => 'parent@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'accepted_terms' => true,
            ])
            ->assertCreated();

        /** @var User $user */
        $user = User::query()->where('email', 'parent@example.com')->firstOrFail();

        $this->assertDatabaseCount('user_consents', 2);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'type' => UserConsent::TYPE_TERMS,
            'document_version' => '2030-01-01',
            'user_agent' => 'ConsentTestBrowser/1.0',
        ]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'type' => UserConsent::TYPE_PRIVACY,
            'document_version' => '2030-02-02',
        ]);
        $this->assertNotNull($user->consents()->first()?->ip_address);
    }

    // ─── consent_current in the me payload ───────────────────────────────────

    /** @test */
    public function me_reports_consent_current_true_after_normal_registration(): void
    {
        $this->withMiddleware()->postJson('/api/auth/register', [
            'name' => 'Fresh Parent',
            'email' => 'fresh@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'accepted_terms' => true,
        ])->assertCreated();

        /** @var User $user */
        $user = User::query()->where('email', 'fresh@example.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => true]);
    }

    /** @test */
    public function google_created_user_reports_consent_current_false(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-id-123');
        $googleUser->shouldReceive('getEmail')->andReturn('google@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Google Parent');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $user = app(GoogleAuthService::class)->findOrCreateUser($googleUser);

        $this->assertDatabaseCount('user_consents', 0);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => false]);
    }

    /** @test */
    public function legacy_user_without_consent_rows_reports_consent_current_false(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => false]);
    }

    /** @test */
    public function version_bump_flips_consent_current_back_to_false(): void
    {
        $user = User::factory()->create();
        UserConsent::factory()->for($user)->create();
        UserConsent::factory()->for($user)->privacy()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => true]);

        config(['legal.terms_version' => '2099-01-01']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => false]);
    }

    // ─── POST /profile/consents (the gate's repair endpoint) ─────────────────

    /** @test */
    public function guest_cannot_post_consents(): void
    {
        $this->postJson('/api/profile/consents', ['accepted_terms' => true])
            ->assertUnauthorized();
    }

    /** @test */
    public function accepting_consents_requires_the_checkbox(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/consents', ['accepted_terms' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms']);

        $this->assertDatabaseCount('user_consents', 0);
    }

    /** @test */
    public function accepting_consents_writes_rows_and_flips_consent_current(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/consents', ['accepted_terms' => true])
            ->assertCreated()
            ->assertJson(['consent_current' => true]);

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'type' => UserConsent::TYPE_TERMS,
            'document_version' => config('legal.terms_version'),
        ]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'type' => UserConsent::TYPE_PRIVACY,
            'document_version' => config('legal.privacy_version'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(['consent_current' => true]);
    }

    /** @test */
    public function record_acceptance_is_idempotent_per_version_even_past_the_controller_guard(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $service->recordAcceptance($user, '1.2.3.4', 'FirstAgent');
        $service->recordAcceptance($user, '5.6.7.8', 'SecondAgent');

        $this->assertDatabaseCount('user_consents', 2);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'ip_address' => '1.2.3.4',
        ]);
    }

    /** @test */
    public function repeated_acceptance_with_current_consent_writes_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/consents', ['accepted_terms' => true])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/consents', ['accepted_terms' => true])
            ->assertOk()
            ->assertJson(['consent_current' => true]);

        $this->assertDatabaseCount('user_consents', 2);
    }

    /** @test */
    public function re_consent_appends_new_rows_instead_of_overwriting(): void
    {
        $user = User::factory()->create();
        UserConsent::factory()->for($user)->create(['document_version' => '2020-01-01']);
        UserConsent::factory()->for($user)->privacy()->create(['document_version' => '2020-01-01']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/consents', ['accepted_terms' => true])
            ->assertCreated();

        // The old acceptance stays as history; the trail is append-only.
        $this->assertDatabaseCount('user_consents', 4);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'document_version' => '2020-01-01',
        ]);
    }
}
