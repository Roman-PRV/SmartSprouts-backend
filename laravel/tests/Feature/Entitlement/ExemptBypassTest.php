<?php

namespace Tests\Feature\Entitlement;

use App\Models\Entitlement\AccessExemption;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * An exemption resolves to Unlimited, so neither gate refuses — but the rows
 * are still written. StartLimitTest proves the same numbers do refuse a Free
 * account, so passing here is a bypass and not a disabled gate.
 */
class ExemptBypassTest extends TestCase
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
    public function an_exempt_account_passes_both_gates_and_is_still_counted(): void
    {
        $user = User::factory()->create();
        AccessExemption::factory()->create(['user_id' => $user->id]);
        $game = $this->arithmeticGame();

        // A Free account would be refused on the fourth open and the second completion.
        foreach ([1, 2, 3, 4] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
            $this->actingAs($user)
                ->postJson($this->submitUrl($game, $level), $this->correctPayloadFor($level))
                ->assertOk();
        }

        // FR-007a: the fair-use report has nothing to watch if the rows are skipped.
        $this->assertDatabaseCount('level_daily_usage', 4);
        $this->assertSame(4, LevelDailyUsage::query()->whereNotNull('completed_at')->count());
    }
}
