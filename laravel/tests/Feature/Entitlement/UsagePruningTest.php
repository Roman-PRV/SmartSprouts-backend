<?php

namespace Tests\Feature\Entitlement;

use App\Models\Entitlement\LevelDailyUsage;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UsagePruningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deletes_rows_past_the_retention_window_and_keeps_the_boundary_day(): void
    {
        config()->set('billing.usage_retention_days', 7);
        Log::spy();

        $user = User::factory()->create();
        $game = Game::factory()->create();

        $boundary = now()->subDays(7)->toDateString();
        $expired = now()->subDays(8)->toDateString();

        foreach ([now()->toDateString(), $boundary, $expired] as $index => $date) {
            LevelDailyUsage::factory()
                ->for($user)
                ->onDay(now()->parse($date))
                ->create(['game_id' => $game->id, 'level_id' => $index + 1]);
        }

        $this->artisan('entitlement:prune-usage')->assertExitCode(0);

        $this->assertDatabaseCount('level_daily_usage', 2);
        $this->assertDatabaseMissing('level_daily_usage', ['usage_date' => $expired]);
        // A row aged exactly the retention window is not yet older than it.
        $this->assertDatabaseHas('level_daily_usage', ['usage_date' => $boundary]);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $context['deleted'] === 1)
            ->once();
    }

    /** @test */
    public function it_refuses_to_run_when_the_retention_window_is_not_positive(): void
    {
        config()->set('billing.usage_retention_days', 0);
        Log::spy();

        LevelDailyUsage::factory()
            ->onDay(now()->subYear())
            ->create();

        $this->artisan('entitlement:prune-usage')
            ->expectsOutputToContain('billing.usage_retention_days')
            ->assertExitCode(1);

        $this->assertDatabaseCount('level_daily_usage', 1);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => str_contains($message, 'BILLING_USAGE_RETENTION_DAYS'))
            ->once();
    }
}
