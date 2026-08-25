<?php

namespace Tests\Unit\Enums\Billing;

use App\Enums\Billing\SubscriptionStatusEnum;
use PHPUnit\Framework\TestCase;

class SubscriptionStatusEnumTest extends TestCase
{
    public function test_there_are_exactly_four_statuses(): void
    {
        $this->assertSame(
            ['active', 'cancelling', 'past_due', 'ended'],
            array_column(SubscriptionStatusEnum::cases(), 'value'),
        );
    }

    /**
     * Cancelling and past-due both still grant the tier: the account keeps what
     * it paid for until the period ends or the grace window closes. Dropping
     * either to Free would cut someone off mid-period.
     */
    public function test_only_ended_stops_granting_the_tier(): void
    {
        $this->assertTrue(SubscriptionStatusEnum::ACTIVE->grantsTier());
        $this->assertTrue(SubscriptionStatusEnum::CANCELLING->grantsTier());
        $this->assertTrue(SubscriptionStatusEnum::PAST_DUE->grantsTier());

        $this->assertFalse(SubscriptionStatusEnum::ENDED->grantsTier());
    }
}
