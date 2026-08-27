<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\Billing\Subscription;
use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use App\Services\Entitlement\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EntitlementService::class);
    }

    /** @test */
    public function an_account_with_nothing_stored_is_free(): void
    {
        $user = User::factory()->create();

        $this->assertSame(TierEnum::FREE, $this->service->resolveTier($user));
        $this->assertFalse($this->service->isExempt($user));
    }

    /** @test */
    public function a_subscription_grants_its_own_tier(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['tier' => TierEnum::PREMIUM]);

        $this->assertSame(TierEnum::PREMIUM, $this->service->resolveTier($user));
    }

    /** @test */
    public function cancelling_and_past_due_still_grant_the_tier(): void
    {
        $cancelling = User::factory()->create();
        Subscription::factory()->for($cancelling)->cancelling()->create(['tier' => TierEnum::PLUS]);

        $pastDue = User::factory()->create();
        Subscription::factory()->for($pastDue)->pastDue()->create(['tier' => TierEnum::PLUS]);

        $this->assertSame(TierEnum::PLUS, $this->service->resolveTier($cancelling));
        $this->assertSame(TierEnum::PLUS, $this->service->resolveTier($pastDue));
    }

    /** @test */
    public function an_ended_subscription_falls_back_to_free(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->ended()->create(['tier' => TierEnum::UNLIMITED]);

        $this->assertSame(TierEnum::FREE, $this->service->resolveTier($user));
    }

    /** @test */
    public function a_pending_downgrade_does_not_change_todays_tier(): void
    {
        $user = User::factory()->create();
        Subscription::factory()
            ->for($user)
            ->pendingDowngradeTo(TierEnum::FREE)
            ->create(['tier' => TierEnum::PREMIUM]);

        $this->assertSame(TierEnum::PREMIUM, $this->service->resolveTier($user));
    }

    /** @test */
    public function an_exemption_grants_unlimited(): void
    {
        $user = User::factory()->create();
        AccessExemption::factory()->for($user)->create();

        $this->assertSame(TierEnum::UNLIMITED, $this->service->resolveTier($user));
        $this->assertTrue($this->service->isExempt($user));
    }

    /** Not a state the app creates; pins the order down anyway. @test */
    public function an_exemption_outranks_a_lower_subscribed_tier(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['tier' => TierEnum::PLUS]);
        AccessExemption::factory()->for($user)->create();

        $this->assertSame(TierEnum::UNLIMITED, $this->service->resolveTier($user));
    }

    /** @test */
    public function paid_unlimited_is_not_exempt(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create(['tier' => TierEnum::UNLIMITED]);

        $this->assertSame(TierEnum::UNLIMITED, $this->service->resolveTier($user));
        $this->assertFalse($this->service->isExempt($user));
    }
}
