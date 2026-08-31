<?php

namespace Tests\Unit\Exceptions\Entitlement;

use App\Enums\Entitlement\LimitKindEnum;
use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\DailyStartedLimitExceededException;
use App\Models\User;
use Tests\TestCase;

class DailyLimitExceededExceptionTest extends TestCase
{
    /**
     * The enum keeps the value spellable only one way; swapping the two cases
     * between the classes stays type-correct, so it takes a test to catch.
     */
    public function test_each_exception_names_its_own_limit_kind(): void
    {
        $user = $this->userWithId(1);

        $this->assertSame(LimitKindEnum::STARTED, DailyStartedLimitExceededException::exceededBy($user, TierEnum::FREE, 3)->limitKind());
        $this->assertSame(LimitKindEnum::COMPLETED, DailyCompletedLimitExceededException::exceededBy($user, TierEnum::FREE, 1)->limitKind());
    }

    /** Swapping the two keys stays type-correct, the same way limitKind() does. */
    public function test_each_exception_names_its_own_message(): void
    {
        $user = $this->userWithId(1);

        $this->assertSame(
            'exceptions.entitlement.daily_started_limit_reached',
            DailyStartedLimitExceededException::exceededBy($user, TierEnum::FREE, 3)->messageKey(),
        );
        $this->assertSame(
            'exceptions.entitlement.daily_completed_limit_reached',
            DailyCompletedLimitExceededException::exceededBy($user, TierEnum::FREE, 1)->messageKey(),
        );
    }

    public function test_exceeded_by_builds_a_message_naming_the_user_tier_and_limit(): void
    {
        $user = $this->userWithId(42);

        $message = DailyStartedLimitExceededException::exceededBy($user, TierEnum::PLUS, 8)->getMessage();

        $this->assertStringContainsString('42', $message);
        $this->assertStringContainsString('plus', $message);
        $this->assertStringContainsString('8', $message);
    }

    /** id is guarded against mass assignment, so it has to be set directly. */
    private function userWithId(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }
}
