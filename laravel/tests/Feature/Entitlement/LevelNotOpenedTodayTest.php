<?php

namespace Tests\Feature\Entitlement;

use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * FR-005c: a completion is only recordable against an open recorded today, so
 * the submit gate refuses when there is none.
 */
class LevelNotOpenedTodayTest extends TestCase
{
    use RefreshDatabase;
    use UsesArithmeticGate;

    /** @test */
    public function submitting_a_level_that_was_never_opened_is_refused(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)
            ->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'LEVEL_NOT_OPENED_TODAY')
            ->assertJsonMissingPath('details');

        $this->assertDatabaseCount('game_results', 0);
    }

    /** @test */
    public function a_level_opened_only_yesterday_is_refused_today(): void
    {
        // The ordinary cause: a tab left open overnight.
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();

        $this->travel(1)->days();

        $this->actingAs($user)
            ->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'LEVEL_NOT_OPENED_TODAY');

        $this->assertDatabaseCount('game_results', 0);
    }

    /** @test */
    public function an_unlimited_account_needs_the_open_too(): void
    {
        // The precondition is not a limit, so no tier is exempt from it.
        $user = User::factory()->create();
        AccessExemption::factory()->create(['user_id' => $user->id]);
        $game = $this->arithmeticGame();

        $this->actingAs($user)
            ->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'LEVEL_NOT_OPENED_TODAY');
    }
}
