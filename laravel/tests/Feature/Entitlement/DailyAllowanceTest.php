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
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class DailyAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private DailyUsageService $service;

    /** Pinned so repricing a tier cannot fail these; TierEnumTest covers the shipped numbers. */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.tiers.free', [
            'completed_limit' => 1,
            'started_limit' => 3,
            'price_minor' => 0,
        ]);

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

    /** @test */
    public function recording_an_open_inside_an_outer_transaction_is_refused(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->expectException(LogicException::class);

        DB::transaction(function () use ($user, $game): void {
            $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        });
    }

    /** @test */
    public function a_tier_with_one_null_limit_still_enforces_the_other(): void
    {
        config()->set('billing.tiers.premium', [
            'completed_limit' => null,
            'started_limit' => 2,
            'price_minor' => 500,
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create();

        // Both completed: a null completed_limit is the only thing keeping this
        // from refusing on the completion counter instead.
        $this->service->recordOpen($user, TierEnum::PREMIUM, $game->id, 1);
        $this->service->recordCompletion($user, $game->id, 1);
        $this->service->recordOpen($user, TierEnum::PREMIUM, $game->id, 2);
        $this->service->recordCompletion($user, $game->id, 2);

        $this->expectException(DailyStartedLimitExceededException::class);

        try {
            $this->service->recordOpen($user, TierEnum::PREMIUM, $game->id, 3);
        } finally {
            $this->assertDatabaseCount('level_daily_usage', 2);
        }
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
