<?php

namespace Tests\Feature\Entitlement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * The restricted state an account enters by refusing the documents in force
 * (FR-012a, FR-012b).
 *
 * Nothing here can be reached by hand: at launch there is no earlier Terms
 * version to decline, so this path first becomes possible at the next document
 * change. These tests are the only thing keeping it alive until then.
 */
class DeclinedTermsTest extends TestCase
{
    use RefreshDatabase;
    use UsesArithmeticGate;

    /** @test */
    public function a_declined_account_cannot_open_or_submit_a_level(): void
    {
        $user = User::factory()->withoutConsent()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'CONSENT_REQUIRED');

        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'CONSENT_REQUIRED');

        // Refused before anything was counted or scored: a restricted account
        // spends no allowance, so returning later finds the day untouched.
        $this->assertDatabaseCount('level_daily_usage', 0);
        $this->assertDatabaseCount('game_results', 0);
    }

    /** @test */
    public function an_account_that_never_answered_is_refused_the_same_way(): void
    {
        $user = User::factory()->withoutConsent()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'CONSENT_REQUIRED');

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('consent_current', false)
            ->assertJsonPath('consent_declined', false);
    }

    /** @test */
    public function a_declined_account_keeps_its_data_subject_rights(): void
    {
        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->actingAs($user)->getJson('/api/profile')->assertOk();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'new_password' => 'NewPassword1',
                'new_password_confirmation' => 'NewPassword1',
            ])
            ->assertOk();
    }

    /** @test */
    public function a_declined_account_can_still_delete_itself(): void
    {
        Mail::fake();

        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function the_decline_is_reported_so_the_client_can_tell_it_from_silence(): void
    {
        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('consent_current', false)
            ->assertJsonPath('consent_declined', true);
    }

    /**
     * The whole path end to end, in the only order it can really happen: an
     * account playing happily under one version, a new one published, the
     * refusal, and the change of mind.
     *
     * @test
     */
    public function accepting_a_new_version_after_refusing_it_restores_play_with_no_data_loss(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();

        config([
            'legal.terms_version' => '2099-01-01',
            'legal.privacy_version' => '2099-01-01',
        ]);

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();
        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertStatus(403);

        $this->actingAs($user)->postJson('/api/profile/consents', ['accepted_terms' => true])->assertCreated();

        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertOk();
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertJsonPath('consent_current', true)
            ->assertJsonPath('consent_declined', false);

        // The play from before the refusal came through it untouched.
        $this->assertDatabaseCount('game_results', 1);
        $this->assertDatabaseCount('level_daily_usage', 2);
    }

    /**
     * Refusing a version already accepted does nothing, and says so with the
     * same 204. The gate only offers the choice while consent is not current,
     * so this is unreachable from the client — but it is the behaviour that
     * keeps this endpoint from quietly becoming a consent-withdrawal feature.
     *
     * @test
     */
    public function refusing_a_version_already_accepted_leaves_access_intact(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertJsonPath('consent_current', true)
            ->assertJsonPath('consent_declined', false);
    }

    /** @test */
    public function accepting_wins_without_deleting_the_refusal(): void
    {
        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();
        $this->actingAs($user)->postJson('/api/profile/consents', ['accepted_terms' => true])->assertCreated();

        // The refusal rows are deliberately left behind: an acceptance of the
        // same version outranks them, and the next version bump leaves them
        // matching nothing in force. Deleting them would be work for no reader.
        $this->assertSame(2, $user->consentDeclines()->count());
        $this->actingAs($user)->getJson('/api/auth/me')->assertJsonPath('consent_declined', false);
    }

    /** @test */
    public function refusing_twice_records_one_answer_per_document(): void
    {
        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();
        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        $this->assertDatabaseCount('user_consent_declines', 2);
    }

    /** @test */
    public function a_refusal_of_a_superseded_version_reads_as_silence(): void
    {
        $user = User::factory()->withoutConsent()->create();

        $this->actingAs($user)->postJson('/api/profile/consents/decline')->assertNoContent();

        config([
            'legal.terms_version' => '2099-01-01',
            'legal.privacy_version' => '2099-01-01',
        ]);

        // A refusal answers one version, not the documents in general — after a
        // bump the account has not answered the new one.
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertJsonPath('consent_current', false)
            ->assertJsonPath('consent_declined', false);
    }

    /** @test */
    public function a_guest_cannot_decline(): void
    {
        $this->postJson('/api/profile/consents/decline')->assertUnauthorized();
    }
}
