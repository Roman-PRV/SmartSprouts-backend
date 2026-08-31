<?php

namespace Tests\Feature\Entitlement;

use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * The submit gate over HTTP: the completion step inside AttemptController@store.
 */
class CompletionLimitTest extends TestCase
{
    use RefreshDatabase;
    use UsesArithmeticGate;

    /** Pinned so repricing a tier cannot fail these; TierEnumTest covers the shipped numbers. */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.tiers.free', [
            'completed_limit' => 1,
            'started_limit' => 3,
            'price_minor' => 0,
        ]);
    }

    /** @test */
    public function opening_the_whole_start_allowance_does_not_buy_extra_completions(): void
    {
        // FR-005a: the start limit is not a back door to the completion limit.
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        foreach ([1, 2, 3] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
        }

        $this->actingAs($user)
            ->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))
            ->assertOk();

        $this->actingAs($user)
            ->postJson($this->submitUrl($game, 2), $this->correctPayloadFor(2))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'LEVEL_LIMIT_REACHED')
            ->assertJsonPath('details.limit_kind', 'completed');
    }

    /** @test */
    public function a_refused_completion_leaves_nothing_behind(): void
    {
        // FR-005b: the refusal and the scoring share one transaction.
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertOk();

        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 2), $this->correctPayloadFor(2))->assertStatus(403);

        $this->assertDatabaseCount('game_results', 1);
        $this->assertDatabaseMissing('game_results', ['level_id' => 2]);
        $this->assertNull(
            LevelDailyUsage::query()->where('level_id', 2)->sole()->completed_at,
        );
    }

    /** @test */
    public function the_completion_allowance_comes_back_after_the_reset(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 2), $this->correctPayloadFor(2))->assertStatus(403);

        $this->travel(1)->days();

        // Yesterday row is not today open, so the level is opened again first.
        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 2), $this->correctPayloadFor(2))->assertOk();
    }

    /** @test */
    public function replaying_a_level_completed_today_costs_nothing(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();

        // Both attempts scored; the counter moved once, on the first.
        $this->assertDatabaseCount('game_results', 2);
        $this->assertSame(1, LevelDailyUsage::query()->whereNotNull('completed_at')->count());
    }
}
