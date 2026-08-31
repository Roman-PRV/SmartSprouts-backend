<?php

namespace Tests\Feature\Entitlement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * The open gate over HTTP: EnforceLevelStart on games.levels.show.
 */
class StartLimitTest extends TestCase
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
    public function the_open_past_the_start_allowance_is_refused(): void
    {
        config()->set('billing.purchasing_enabled', true);

        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        foreach ([1, 2, 3] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
        }

        $this->actingAs($user)->getJson($this->openUrl($game, 4))
            ->assertStatus(403)
            ->assertJsonPath('error_type', 'LEVEL_LIMIT_REACHED')
            ->assertJsonPath('details.limit_kind', 'started')
            ->assertJsonPath('details.purchasing_enabled', true)
            ->assertJsonPath('details.resets_at', now()->addDay()->startOfDay()->toIso8601ZuluString());

        // The refused open rolled back with recordOpen()'s own transaction.
        $this->assertDatabaseCount('level_daily_usage', 3);
    }

    /** @test */
    public function reopening_a_level_already_opened_today_is_free(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        foreach ([1, 2, 3] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
        }

        // FR-003b: a fourth request is a fourth open only when it is a new level.
        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();

        $this->assertDatabaseCount('level_daily_usage', 3);
    }

    /** @test */
    public function a_level_that_does_not_exist_is_not_an_open(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        // Past the catalogue: a stale link to a level the admin removed must
        // not cost the day.
        $this->actingAs($user)->getJson($this->openUrl($game, 99))->assertStatus(404);

        $this->assertDatabaseCount('level_daily_usage', 0);
    }

    /** @test */
    public function listing_levels_is_not_an_open(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson("/api/games/{$game->id}/levels")->assertOk();

        $this->assertDatabaseCount('level_daily_usage', 0);
    }
}
