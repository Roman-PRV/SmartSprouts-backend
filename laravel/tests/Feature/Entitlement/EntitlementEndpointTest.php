<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Billing\SubscriptionStatusEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\Billing\Subscription;
use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Entitlement\Concerns\UsesArithmeticGate;
use Tests\TestCase;

/**
 * GET /api/entitlement — the payload the whole frontend billing module is
 * written against.
 *
 * Usage is driven through real requests rather than inserted rows: the point of
 * the endpoint is that the number on screen and a refusal come from one count,
 * and only the real path proves it.
 */
class EntitlementEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesArithmeticGate;

    /** Pinned so repricing a tier cannot fail these; TierEnumTest covers the shipped numbers. */
    protected function setUp(): void
    {
        parent::setUp();

        // Both counters hang off the UTC date and resets_at off the next
        // midnight, so a frozen clock lets the expectations below be dates
        // rather than a second copy of the arithmetic under test.
        $this->travelTo(Carbon::parse('2026-08-27 10:00:00'));

        config()->set('billing.tiers.free', [
            'completed_limit' => 1,
            'started_limit' => 3,
            'price_minor' => 0,
        ]);

        config()->set('billing.tiers.premium', [
            'completed_limit' => 20,
            'started_limit' => 40,
            'price_minor' => 500,
        ]);
    }

    /** @test */
    public function a_free_account_reports_what_todays_play_left(): void
    {
        config()->set('billing.purchasing_enabled', true);

        $user = User::factory()->create();
        $game = $this->arithmeticGame();

        $this->actingAs($user)->getJson($this->openUrl($game, 1))->assertOk();
        $this->actingAs($user)->postJson($this->submitUrl($game, 1), $this->correctPayloadFor(1))->assertOk();
        $this->actingAs($user)->getJson($this->openUrl($game, 2))->assertOk();

        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            ->assertJsonPath('tier', 'free')
            ->assertJsonPath('is_exempt', false)
            ->assertJsonPath('limits', ['completed' => 1, 'started' => 3])
            ->assertJsonPath('remaining', ['completed' => 0, 'started' => 1])
            ->assertJsonPath('resets_at', '2026-08-28T00:00:00Z')
            ->assertJsonPath('purchasing_enabled', true)
            ->assertJsonPath('subscription', null);
    }

    /** @test */
    public function an_exemption_reports_unlimited_with_no_limits_at_all(): void
    {
        $user = User::factory()->create();
        AccessExemption::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            // The same tier a paying Unlimited customer sees; is_exempt is the
            // only field that separates them.
            ->assertJsonPath('tier', 'unlimited')
            ->assertJsonPath('is_exempt', true)
            ->assertJsonPath('limits', ['completed' => null, 'started' => null])
            ->assertJsonPath('remaining', ['completed' => null, 'started' => null]);
    }

    /** @test */
    public function revoking_an_exemption_mid_day_leaves_remaining_at_zero(): void
    {
        $user = User::factory()->create();
        $exemption = AccessExemption::factory()->create(['user_id' => $user->id]);
        $game = $this->arithmeticGame();

        // Four opens and two completions — past what Free allows, which the
        // exemption permitted at the time.
        foreach ([1, 2, 3, 4] as $level) {
            $this->actingAs($user)->getJson($this->openUrl($game, $level))->assertOk();
        }

        foreach ([1, 2] as $level) {
            $this->actingAs($user)->postJson($this->submitUrl($game, $level), $this->correctPayloadFor($level))->assertOk();
        }

        $exemption->delete();

        // actingAs() keeps one User instance for the whole test, and its
        // relation is loaded from the requests above. A real later request
        // starts from a fresh model.
        $user->refresh();

        // The raw subtraction is negative on both counters.
        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            ->assertJsonPath('tier', 'free')
            ->assertJsonPath('is_exempt', false)
            ->assertJsonPath('remaining', ['completed' => 0, 'started' => 0]);
    }

    /** @test */
    public function a_scheduled_downgrade_lands_at_the_period_boundary(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()
            ->pendingDowngradeTo(TierEnum::FREE)
            ->create(['user_id' => $user->id, 'tier' => TierEnum::PREMIUM]);

        $periodEnd = $subscription->current_period_end->toIso8601ZuluString();

        $response = $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            // Still Premium: the queued tier is not today's.
            ->assertJsonPath('tier', 'premium')
            // The allowances follow the resolved tier, not the queued one.
            ->assertJsonPath('limits', ['completed' => 20, 'started' => 40])
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.current_period_end', $periodEnd)
            ->assertJsonPath('subscription.pending_tier', 'free')
            ->assertJsonPath('subscription.cancel_at_period_end', false)
            ->assertJsonPath('subscription.manage_url', null);

        // The downgrade takes effect at the boundary above, so a second date
        // saying the same thing does not travel.
        $this->assertArrayNotHasKey('pending_tier_effective_at', $response->json('subscription'));
    }

    /** @test */
    public function undoing_a_cancellation_stops_announcing_the_end(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->cancelling()->create(['user_id' => $user->id]);

        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            ->assertJsonPath('subscription.status', 'cancelling')
            ->assertJsonPath('subscription.cancel_at_period_end', true);

        $subscription->update(['status' => SubscriptionStatusEnum::ACTIVE]);
        $user->refresh();

        // cancelled_at keeps its timestamp through the undo, so reading the flag
        // off that column would still say the subscription is ending.
        $this->assertNotNull($subscription->fresh()?->cancelled_at);

        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.cancel_at_period_end', false);
    }

    /** @test */
    public function an_ended_subscription_reports_free_beside_the_closed_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->ended()->create(['user_id' => $user->id, 'tier' => TierEnum::PREMIUM]);

        $this->actingAs($user)->getJson('/api/entitlement')
            ->assertOk()
            ->assertJsonPath('tier', 'free')
            ->assertJsonPath('limits', ['completed' => 1, 'started' => 3])
            // The block stays rather than going null: a closed subscription is
            // what the client offers to resume, and hiding it would make this
            // account indistinguishable from one that never paid.
            ->assertJsonPath('subscription.status', 'ended')
            // The boundary is in the past here, and the field name no longer
            // claims otherwise.
            ->assertJsonPath('subscription.current_period_end', '2026-08-26T10:00:00Z')
            ->assertJsonPath('subscription.cancel_at_period_end', false);
    }

    /** @test */
    public function the_catalogue_carries_every_tier_in_ladder_order_under_one_currency(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson('/api/entitlement')->assertOk();

        $response->assertJsonPath('currency', 'EUR');
        $response->assertJsonCount(4, 'tiers');
        $response->assertJsonPath('tiers.0.key', 'free');
        $response->assertJsonPath('tiers.1.key', 'plus');
        $response->assertJsonPath('tiers.2.key', 'premium');
        $response->assertJsonPath('tiers.3.key', 'unlimited');

        // The plan page renders prices and allowances from here and holds no
        // copy of its own (FR-014b).
        $response->assertJsonPath('tiers.0.price_minor', 0);
        $response->assertJsonPath('tiers.0.limits', ['completed' => 1, 'started' => 3]);
        $response->assertJsonPath('tiers.3.limits', ['completed' => null, 'started' => null]);

        // One currency for all four, beside the array rather than inside it.
        foreach ($response->json('tiers') as $tier) {
            $this->assertArrayNotHasKey('currency', $tier);
        }
    }

    /** @test */
    public function the_endpoint_is_behind_authentication(): void
    {
        $this->getJson('/api/entitlement')->assertStatus(401);
    }
}
