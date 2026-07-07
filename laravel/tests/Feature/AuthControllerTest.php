<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_register_successfully(): void
    {
        $response = $this->withMiddleware()->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['access_token']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials(): void
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token']);
    }

    /** @test */
    public function login_fails_with_invalid_credentials(): void
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
        ]);
    }

    /** @test */
    public function invalid_credentials_message_is_localized(): void
    {
        User::factory()->create([
            'email' => 'loc@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->withHeaders(['Accept-Language' => 'uk'])
            ->postJson('/api/auth/login', [
                'email' => 'loc@example.com',
                'password' => 'wrongpass',
            ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Невірні облікові дані']);
    }

    /** @test */
    public function login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'target@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'target@example.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'target@example.com',
            'password' => 'wrong',
        ])->assertStatus(429)->assertHeader('Retry-After');
    }

    /** @test */
    public function login_throttle_is_scoped_per_email_not_shared_across_accounts(): void
    {
        // Exhaust the per-account limit for one email from this IP.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong']);
        }

        // A different email from the same IP still gets a normal 401, not 429.
        $this->postJson('/api/auth/login', ['email' => 'b@example.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    /** @test */
    public function login_ip_limit_caps_password_spraying_across_emails(): void
    {
        // Each email is distinct, so the per-account (email+ip) limit never
        // trips; the 21st attempt hits the per-ip spraying cap regardless.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', ['email' => "spray{$i}@example.com", 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/login', ['email' => 'spray-last@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    /** @test */
    public function registration_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])->assertCreated();
        }

        $this->postJson('/api/auth/register', [
            'name' => 'User 41',
            'email' => 'user41@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(429);
    }

    /** @test */
    public function google_callback_is_throttled_per_ip(): void
    {
        // Throttle runs before the controller, so no OAuth state/mocking is needed.
        for ($i = 0; $i < 40; $i++) {
            $this->getJson('/api/auth/google/callback');
        }

        $this->getJson('/api/auth/google/callback')->assertStatus(429);
    }

    /** @test */
    public function google_callback_bucket_is_separate_from_register(): void
    {
        // Exhaust the register bucket from this IP.
        for ($i = 0; $i < 40; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);
        }

        // A shared bucket would already return 429 here; a separate one still
        // lets the callback through (302 redirect to the frontend).
        $this->getJson('/api/auth/google/callback')->assertStatus(302);
    }

    /** @test */
    public function registration_fails_without_password_confirmation(): void
    {
        $response = $this->withMiddleware()->postJson('/api/auth/register', [
            'name' => 'Edge User',
            'email' => 'edge@example.com',
            'password' => 'password123',
            // no password_confirmation
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function validation_errors_use_the_unified_json_contract(): void
    {
        // Pins the API-wide 422 shape ({message, errors}) on a non-game endpoint,
        // so the unification stays locked beyond the game submit flow.
        $response = $this->withMiddleware()
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/auth/register', [
                'name' => 'Edge User',
                'email' => 'not-an-email',
                'password' => 'short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $response->assertJson(['message' => 'Validation failed']);
    }

    /** @test */
    public function user_can_retrieve_own_profile(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_retrieve_profile(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    /** @test */
    public function logout_revokes_only_the_current_device_token(): void
    {
        $user = User::factory()->create();

        $phone = $user->createToken('phone');
        $tablet = $user->createToken('tablet');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($phone->plainTextToken)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Successfully logged out']);

        // Assert via the DB, not by re-authenticating: the sanctum guard
        // memoizes the resolved user within one test, so re-auth would still
        // "succeed" on the revoked token.
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tablet->accessToken->getKey()]);
    }
}
