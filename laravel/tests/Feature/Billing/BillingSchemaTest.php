<?php

namespace Tests\Feature\Billing;

use App\Models\Billing\Purchase;
use App\Models\Billing\Subscription;
use App\Models\Entitlement\AccessExemption;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rules this feature stores in its schema rather than in its code.
 *
 * Every one of them fails silently: a wrong delete rule destroys records
 * without an error, and a missing unique key turns a replay into a second
 * start. None of it can be spotted by reading a migration six months from now,
 * which is why the intent is pinned here instead of only being described.
 */
class BillingSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The single most consequential line in the schema. `game_results`
     * cascades, and copying that word into `purchases` would erase accounting
     * records Spanish law requires kept for six years — with no error and
     * nothing left to notice.
     */
    public function test_deleting_a_buyer_detaches_their_purchases_instead_of_deleting_them(): void
    {
        $user = User::factory()->create();
        $purchase = Purchase::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'user_id' => null,
            'user_email_hash' => $purchase->user_email_hash,
        ]);
    }

    /**
     * The mirror of the rule above: everything that is not an accounting record
     * has no retention duty and must go with the account.
     */
    public function test_deleting_an_account_removes_its_usage_subscription_and_exemption(): void
    {
        $user = User::factory()->create();
        LevelDailyUsage::factory()->for($user)->create();
        Subscription::factory()->for($user)->create();
        AccessExemption::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('level_daily_usage', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('access_exemptions', 0);
    }

    /**
     * The unique key is not an optimisation — it is the rule that replaying a
     * level is free, and the arbiter when two devices open the same level at
     * once. Without it the second insert would be counted as a second start.
     */
    public function test_the_same_level_cannot_be_recorded_twice_on_one_day(): void
    {
        $first = LevelDailyUsage::factory()->create();

        $this->expectException(QueryException::class);

        LevelDailyUsage::factory()->create([
            'user_id' => $first->user_id,
            'usage_date' => $first->usage_date,
            'game_id' => $first->game_id,
            'level_id' => $first->level_id,
        ]);
    }

    /**
     * A deviation from data-model.md, taken deliberately: cascading here would
     * revoke a tester's access as a side effect of an unrelated account being
     * deleted, and a non-nullable key would block that deletion outright.
     */
    public function test_an_exemption_outlives_the_admin_who_granted_it(): void
    {
        $admin = User::factory()->admin()->create();
        $exemption = AccessExemption::factory()->create(['granted_by' => $admin->id]);

        $admin->delete();

        $this->assertDatabaseHas('access_exemptions', [
            'id' => $exemption->id,
            'granted_by' => null,
        ]);
    }
}
