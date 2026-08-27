<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeReplayTest extends TestCase
{
    use RefreshDatabase;

    private DailyUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyUsageService::class);
    }

    /** @test */
    public function reopening_an_uncompleted_level_moves_neither_allowance(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $replay = $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);

        // Free allows 3 starts. If the replay above had spent one, the third
        // distinct level below would refuse instead of succeeding.
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 2);
        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 3);

        $this->assertFalse($replay);
        $this->assertDatabaseCount('level_daily_usage', 3);
    }

    /** @test */
    public function reopening_a_completed_level_moves_neither_allowance(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);
        $this->service->recordCompletion($user, $game->id, 1);

        // Free's completion allowance is already spent; a genuinely new open
        // here would throw. A replay must not, because the unique-key
        // collision is caught before the limit check ever runs.
        $replay = $this->service->recordOpen($user, TierEnum::FREE, $game->id, 1);

        $this->assertFalse($replay);
        $this->assertDatabaseCount('level_daily_usage', 1);
        $this->assertNotNull(
            LevelDailyUsage::query()->where('level_id', 1)->sole()->completed_at,
        );
    }
}
