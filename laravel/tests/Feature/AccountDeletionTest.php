<?php

namespace Tests\Feature;

use App\Mail\AccountDeletedMail;
use App\Mail\AccountDeletionCodeMail;
use App\Models\Game;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    // ─── Access ──────────────────────────────────────────────────────────────

    /** @test */
    public function guest_cannot_delete_profile_or_request_a_code(): void
    {
        $this->deleteJson('/api/profile')->assertUnauthorized();
        $this->postJson('/api/profile/deletion-code')->assertUnauthorized();
    }

    // ─── Password path ───────────────────────────────────────────────────────

    /** @test */
    public function password_account_is_not_deleted_with_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['password' => 'not-the-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /** @test */
    public function password_account_cannot_substitute_a_code_for_the_password(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        // The request has no input to correct, so a code request from a
        // password account is a state conflict (409), not a validation error.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/deletion-code')
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /** @test */
    public function password_account_is_deleted_with_the_correct_password(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->createToken('auth_token');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Mail::assertQueued(AccountDeletedMail::class, function (AccountDeletedMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->name === $user->name;
        });
    }

    /** @test */
    public function deletion_cascades_game_results_but_keeps_anonymized_consents(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        UserConsent::factory()->for($user)->create();
        UserConsent::factory()->for($user)->privacy()->create();
        $game = Game::factory()->create();
        $user->gameResults()->create([
            'game_id' => $game->id,
            'level_id' => 1,
            'locale' => 'en',
            'score' => 10,
            'total_questions' => 10,
            'details' => [],
        ]);

        $emailHash = hash_hmac('sha256', mb_strtolower($user->email), 'testing-legal-hash-key');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $this->assertDatabaseCount('game_results', 0);
        $this->assertDatabaseCount('user_consents', 2);
        $this->assertDatabaseMissing('user_consents', ['email_hash' => null]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => null,
            'email_hash' => $emailHash,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }

    // ─── One-time code path (password-less Google accounts) ─────────────────

    /** @test */
    public function passwordless_account_receives_a_code_and_is_deleted_with_it(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/deletion-code')
            ->assertOk();

        $code = null;
        Mail::assertSent(AccountDeletionCodeMail::class, function (AccountDeletionCodeMail $mail) use ($user, &$code) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['code' => $code])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Mail::assertQueued(AccountDeletedMail::class);
    }

    /** @test */
    public function passwordless_account_is_not_deleted_with_a_wrong_or_missing_code(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/deletion-code')
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        Mail::assertNotQueued(AccountDeletedMail::class);
    }

    /** @test */
    public function deletion_code_expires_after_its_ttl(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/deletion-code')
            ->assertOk();

        $code = null;
        Mail::assertSent(AccountDeletionCodeMail::class, function (AccountDeletionCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->travel(11)->minutes();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ─── Rate limits ─────────────────────────────────────────────────────────

    /** @test */
    public function code_requests_are_throttled_per_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/profile/deletion-code')
                ->assertOk();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/deletion-code')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    /** @test */
    public function deletion_attempts_are_throttled_per_user(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user, 'sanctum')
                ->deleteJson('/api/profile', ['password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['password' => 'wrong'])
            ->assertStatus(429);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ─── Email localization ──────────────────────────────────────────────────

    /** @test */
    public function deletion_emails_follow_the_request_locale(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Accept-Language' => 'uk'])
            ->postJson('/api/profile/deletion-code')
            ->assertOk();

        Mail::assertSent(AccountDeletionCodeMail::class, function (AccountDeletionCodeMail $mail) {
            return $mail->locale === 'uk';
        });
    }
}
