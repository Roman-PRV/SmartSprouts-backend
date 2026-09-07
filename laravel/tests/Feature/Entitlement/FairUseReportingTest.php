<?php

namespace Tests\Feature\Entitlement;

use App\Models\Entitlement\AccessExemption;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * The fair-use report is a signal, never a cap: an account well past the
 * threshold plays on untouched and only shows up in a list.
 */
class FairUseReportingTest extends TestCase
{
    use RefreshDatabase;
    use UsesArithmeticGate;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.fair_use_review_threshold', 3);
    }

    /** @test */
    public function an_account_past_the_threshold_plays_unimpeded_and_is_reported(): void
    {
        $user = User::factory()->create();
        AccessExemption::factory()->create(['user_id' => $user->id]);
        $game = $this->arithmeticGame();

        $quiet = User::factory()->create();
        LevelDailyUsage::factory()->for($quiet)->create(['game_id' => $game->id, 'level_id' => 1]);

        $day = now()->toDateString();

        foreach ([1, 2, 3, 4, 5] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
            $this->actingAs($user)
                ->postJson($this->submitUrl($game, $level), $this->correctPayloadFor($level))
                ->assertOk();
        }

        // The report reads the previous full day, so it is run from the next one.
        $this->travel(1)->day();

        $this->artisan('entitlement:flag-fair-use')
            ->expectsOutputToContain("1 account(s) opened more than 3 levels on {$day}")
            ->assertExitCode(0);

        // Report only: every row it counted is still there.
        $this->assertDatabaseCount('level_daily_usage', 6);
    }

    /** @test */
    public function it_reports_an_earlier_day_on_request(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();
        $day = now()->subDays(3);

        foreach ([1, 2, 3, 4] as $level) {
            LevelDailyUsage::factory()
                ->for($user)
                ->onDay($day)
                ->create(['game_id' => $game->id, 'level_id' => $level]);
        }

        $this->artisan('entitlement:flag-fair-use', ['--date' => $day->toDateString()])
            ->expectsOutputToContain("1 account(s) opened more than 3 levels on {$day->toDateString()}")
            ->assertExitCode(0);

        // An empty flag arrives as '', not null, which a plain `??` would pass on.
        $this->artisan('entitlement:flag-fair-use', ['--date' => ''])
            ->expectsOutputToContain('No usage was recorded on '.now()->subDay()->toDateString())
            ->assertExitCode(0);
    }

    /** @test */
    public function it_separates_a_quiet_day_from_one_with_nothing_left_to_read(): void
    {
        $user = User::factory()->create();
        $game = $this->arithmeticGame();
        $quietDay = now()->subDays(2);
        Log::spy();

        LevelDailyUsage::factory()
            ->for($user)
            ->onDay($quietDay)
            ->create(['game_id' => $game->id, 'level_id' => 1]);

        $this->artisan('entitlement:flag-fair-use', ['--date' => $quietDay->toDateString()])
            ->expectsOutputToContain('No account opened more than 3 levels')
            ->assertExitCode(0);

        $this->artisan('entitlement:flag-fair-use', ['--date' => now()->subDays(30)->toDateString()])
            ->expectsOutputToContain('may already have been pruned')
            ->assertExitCode(0);

        // Silence is the commonest outcome, so it has to be the one that proves
        // the report ran at all.
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $context['rows_recorded'] === true)
            ->once();
    }

    /** @test */
    public function it_refuses_a_day_that_has_not_happened_yet(): void
    {
        $this->artisan('entitlement:flag-fair-use', ['--date' => now()->addDay()->toDateString()])
            ->expectsOutputToContain('has not happened yet')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_refuses_a_date_that_is_not_a_real_day(): void
    {
        $this->artisan('entitlement:flag-fair-use', ['--date' => '2026-02-31'])
            ->expectsOutputToContain('--date must be a real day')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_refuses_to_run_when_the_threshold_is_not_positive(): void
    {
        config()->set('billing.fair_use_review_threshold', 0);
        Log::spy();

        $this->artisan('entitlement:flag-fair-use')
            ->expectsOutputToContain('billing.fair_use_review_threshold')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => str_contains($message, 'BILLING_FAIR_USE_REVIEW_THRESHOLD'))
            ->once();
    }
}
