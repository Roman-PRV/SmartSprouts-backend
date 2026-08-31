<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\LevelNotOpenedTodayException;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyResetTest extends TestCase
{
    use RefreshDatabase;

    private DailyUsageService $service;

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
    public function yesterdays_starts_do_not_count_toward_todays_start_limit(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        // Free's start limit is 3; yesterday already used all of it.
        LevelDailyUsage::factory()
            ->for($user)
            ->onDay(now()->subDay())
            ->create(['game_id' => $game->id, 'level_id' => 1]);
        LevelDailyUsage::factory()
            ->for($user)
            ->onDay(now()->subDay())
            ->create(['game_id' => $game->id, 'level_id' => 2]);
        LevelDailyUsage::factory()
            ->for($user)
            ->onDay(now()->subDay())
            ->create(['game_id' => $game->id, 'level_id' => 3]);

        $isNew = $this->service->recordOpen($user, TierEnum::FREE, $game->id, 4);

        $this->assertTrue($isNew);
        $this->assertDatabaseCount('level_daily_usage', 4);
    }

    /** @test */
    public function yesterdays_completions_do_not_count_toward_todays_completion_limit(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        // Free's completion limit is 1; yesterday already used it.
        LevelDailyUsage::factory()
            ->for($user)
            ->completed()
            ->onDay(now()->subDay())
            ->create(['game_id' => $game->id, 'level_id' => 1]);

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 2);

        // Asserted through recordCompletion(), the only place the completion
        // counter is read: recordOpen() would pass here even with the date
        // filter broken, since it never looks at completions at all.
        $marked = DB::transaction(
            fn (): bool => $this->service->recordCompletion($user, TierEnum::FREE, $game->id, 2),
        );

        $this->assertTrue($marked);
        $this->assertDatabaseCount('level_daily_usage', 2);
    }

    /**
     * Unlike the two tests above, this one actually crosses the boundary
     * instead of only dating rows on the other side of it — the service's own
     * clock read is what could break here, not a factory-set column.
     *
     * @test
     */
    public function the_start_allowance_is_restored_after_midnight(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->travelTo(Carbon::parse('2026-08-27 23:50:00'));

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 2);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 3);

        $this->travelTo(Carbon::parse('2026-08-28 00:10:00'));

        $this->assertTrue($this->service->recordOpen($user, TierEnum::FREE, $game->id, 4));
        $this->assertDatabaseCount('level_daily_usage', 4);
    }

    /**
     * Deliberate for now, not an oversight — see the note on recordCompletion().
     *
     * @test
     */
    public function completing_a_level_opened_the_day_before_is_refused(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->travelTo(Carbon::parse('2026-08-27 23:58:00'));
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);

        $this->travelTo(Carbon::parse('2026-08-28 00:03:00'));

        $this->expectException(LevelNotOpenedTodayException::class);

        try {
            DB::transaction(fn (): bool => $this->service->recordCompletion($user, TierEnum::FREE, $game->id, 1));
        } finally {
            $this->assertNull(LevelDailyUsage::query()->where('level_id', 1)->sole()->completed_at);
        }
    }
}
