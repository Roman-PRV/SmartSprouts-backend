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
    public function successful_update_returns_204_and_changes_password_in_db(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->user->fresh()->password));
    }

    /** @test */
    public function successful_update_revokes_other_tokens_but_keeps_current_token(): void
    {
        $currentToken = $this->user->createToken('current-device');
        $otherToken = $this->user->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->putJson(self::ENDPOINT, $this->validPayload())
            ->assertNoContent();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        // Verify the current token remains usable after the password change.
        $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/profile')
            ->assertOk();
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
            ->assertNoContent();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }
}
