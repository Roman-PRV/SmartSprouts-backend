<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\DailyStartedLimitExceededException;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Free tier throughout: started_limit=3, completed_limit=1 (config/billing.php).
 */
class DailyAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private DailyUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyUsageService::class);
    }

    /** @test */
    public function opening_a_new_level_consumes_only_the_start_allowance(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);

        $this->assertDatabaseCount('level_daily_usage', 1);
        $this->assertDatabaseHas('level_daily_usage', [
            'user_id' => $user->id,
            'level_id' => 1,
            'completed_at' => null,
        ]);
    }

    /** @test */
    public function completing_a_level_consumes_only_the_completion_allowance(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordCompletion($user, $game->id, 1);

        // Still one row: completing marks the existing row, it does not add one.
        $this->assertDatabaseCount('level_daily_usage', 1);
        $this->assertDatabaseHas('level_daily_usage', [
            'user_id' => $user->id,
            'level_id' => 1,
        ]);
        $this->assertNotNull(
            LevelDailyUsage::query()->where('level_id', 1)->sole()->completed_at,
        );
    }

    /** @test */
    public function the_start_limit_refuses_independently_and_names_itself(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        // Three opens, none completed: only the start allowance is at stake.
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 2);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 3);

        $this->expectException(DailyStartedLimitExceededException::class);

        try {
            $this->service->recordOpen($user, TierEnum::FREE, $game->id, 4);
        } finally {
            // The refused open must not survive the rollback.
            $this->assertDatabaseCount('level_daily_usage', 3);
        }
    }

    /** @test */
    public function the_completion_limit_refuses_independently_and_names_itself(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        // One completion exhausts Free's completion allowance while only one
        // of the three starts has been spent — the start limit has headroom.
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordCompletion($user, $game->id, 1);

        $this->expectException(DailyCompletedLimitExceededException::class);

        try {
            $this->service->recordOpen($user, TierEnum::FREE, $game->id, 2);
        } finally {
            $this->assertDatabaseCount('level_daily_usage', 1);
        }
    }

    /**
     * Unlimited has no configured limit for either counter, so
     * assertWithinAllowance never runs a count for it — recording still
     * happens, past what Free's limits would have refused (FR-007a).
     *
     * @test
     */
    public function an_unlimited_tier_is_recorded_without_being_checked(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        for ($levelId = 1; $levelId <= 5; $levelId++) {
            $this->service->recordOpen($user, TierEnum::UNLIMITED, $game->id, $levelId);
            $this->service->recordCompletion($user, $game->id, $levelId);
        }

        $this->assertDatabaseCount('level_daily_usage', 5);
    }

    /**
     * A completed level is a spent slot of the completion allowance; marking
     * it twice would let a paying account complete more than it paid for.
     *
     * @test
     */
    public function completing_the_same_level_twice_marks_it_only_once(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordCompletion($user, $game->id, 1);

        $markedAt = LevelDailyUsage::query()->sole()->completed_at;

        $this->travel(5)->minutes();
        $secondCall = $this->service->recordCompletion($user, $game->id, 1);

        $this->assertFalse($secondCall);
        $this->assertEquals($markedAt, LevelDailyUsage::query()->sole()->completed_at);
    }

    /** @test */
    public function completing_a_level_with_no_open_recorded_today_marks_nothing(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->assertFalse($this->service->recordCompletion($user, $game->id, 1));
        $this->assertDatabaseCount('level_daily_usage', 0);
    }
}
