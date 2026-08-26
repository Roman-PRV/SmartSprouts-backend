<?php

namespace Tests\Feature\Billing;

use App\Models\Billing\Purchase;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two properties of this feature's fixtures that later issues will lean on
 * without checking, and that would break far from where they were caused.
 */
class BillingFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chaining these two states depends on how Laravel folds one state into the
     * next. Swap the two lines and the factory starts producing a level
     * completed months before it was opened — and the failure surfaces in an
     * allowance test two issues away, pointing at the wrong thing.
     */
    public function test_the_day_and_completion_states_compose_in_either_order(): void
    {
        $day = now()->subDays(3);

        $rows = [
            LevelDailyUsage::factory()->onDay($day)->completed()->make(),
            LevelDailyUsage::factory()->completed()->onDay($day)->make(),
        ];

        foreach ($rows as $usage) {
            $this->assertSame($day->toDateString(), $usage->usage_date->toDateString());
            $this->assertSame($day->toDateString(), $usage->opened_at->toDateString());
            $this->assertSame($day->toDateString(), $usage->completed_at->toDateString());
        }
    }

    /**
     * The hash is how a purchase is found once the account is gone, so two rows
     * for one person must carry the same one. A random value per row would let
     * a "re-registering does not reset the refund allowance" test look correct
     * while matching nothing.
     */
    public function test_two_purchases_by_one_person_carry_the_same_email_hash(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            Purchase::factory()->for($user)->create()->user_email_hash,
            Purchase::factory()->for($user)->create()->user_email_hash,
        );
    }
}
