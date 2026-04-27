<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/profile/password';

    private const CURRENT_PASSWORD = 'OldPassword1';

    private const NEW_PASSWORD = 'NewPassword1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => Hash::make(self::CURRENT_PASSWORD),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'current_password' => self::CURRENT_PASSWORD,
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_user_gets_401(): void
    {
        $this->putJson(self::ENDPOINT, [])->assertUnauthorized();
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    /** @test */
    public function wrong_current_password_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload([
                'current_password' => 'WrongPassword1',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function new_password_too_short_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload([
                'new_password' => 'Abc1',
                'new_password_confirmation' => 'Abc1',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function new_password_without_mixed_case_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload([
                'new_password' => 'newpassword1',
                'new_password_confirmation' => 'newpassword1',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function new_password_without_numbers_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload([
                'new_password' => 'NewPassword',
                'new_password_confirmation' => 'NewPassword',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /** @test */
    public function new_password_confirmation_mismatch_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload([
                'new_password_confirmation' => 'DifferentPassword1',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    // ─── Successful update ────────────────────────────────────────────────────

    /** @test */
    public function successful_update_returns_200_with_new_token(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    /** @test */
    public function successful_update_changes_password_in_db(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->user->fresh()->password));
    }

    /** @test */
    public function successful_update_revokes_all_existing_tokens_and_issues_a_fresh_one(): void
    {
        $oldToken1 = $this->user->createToken('device-1');
        $oldToken2 = $this->user->createToken('device-2');

        $response = $this->withToken($oldToken1->plainTextToken)
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk();

        // Both old tokens must be gone.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken1->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken2->accessToken->id]);

        // Exactly one new token must exist for this user.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The fresh token returned in the response must be the only remaining one.
        $newPlainToken = $response->json('access_token');
        $this->getJson('/api/profile', ['Authorization' => "Bearer {$newPlainToken}"])
            ->assertOk();
    }

    /** @test */
    public function old_bearer_token_is_invalidated_after_password_change(): void
    {
        $oldToken = $this->user->createToken('old-session');

        $this->withToken($oldToken->plainTextToken)
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk();

        // Assert the token row is gone from the DB — Sanctum looks up the token
        // on every request, so a deleted row means the token is permanently invalid.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);
    }

    /**
     * Covers the non-PAT path: actingAs() sets a TransientToken (not a PersonalAccessToken).
     * All existing DB tokens must still be revoked regardless of how the request is authenticated.
     *
     * @test
     */
    public function session_authenticated_request_revokes_all_existing_tokens(): void
    {
        $existingToken1 = $this->user->createToken('device-1');
        $existingToken2 = $this->user->createToken('device-2');

        // actingAs() uses a TransientToken — simulates web/session guard or no PAT context.
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);

        // All pre-existing tokens must be gone.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $existingToken1->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $existingToken2->accessToken->id]);

        // Only the newly issued token remains.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /** @test */
    public function successful_update_deletes_password_reset_tokens_for_user(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => 'some-reset-token',
            'created_at' => now(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }
}
