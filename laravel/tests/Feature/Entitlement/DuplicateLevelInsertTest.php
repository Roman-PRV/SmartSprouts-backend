<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers only the unique-index arbitration of a repeated open of the SAME
 * level (FR-006). The other half of FR-006 — two DIFFERENT levels opened at
 * once, defended by the locking read in assertWithinAllowance — needs real
 * row locking, which sqlite does not have, so it is not covered by an
 * automated test (see issues/04-BE-daily-usage-engine.md).
 */
class DuplicateLevelInsertTest extends TestCase
{
    use RefreshDatabase;

    private DailyUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyUsageService::class);
    }

    /** @test */
    public function two_opens_of_the_same_level_produce_exactly_one_row(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $first = $this->service->recordOpen($user, TierEnum::FREE, $game->id, 5);
        $second = $this->service->recordOpen($user, TierEnum::FREE, $game->id, 5);

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertDatabaseCount('level_daily_usage', 1);
    }

    /** @test */
    public function the_collision_decrements_the_start_allowance_exactly_once(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 5);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 5);

        // Free allows 3 starts. If the collision above had counted twice,
        // only one of these two distinct levels would fit.
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 6);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 7);

        $this->assertDatabaseCount('level_daily_usage', 3);
    }
}
