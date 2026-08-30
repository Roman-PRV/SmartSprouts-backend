<?php

namespace Tests\Unit\Exceptions\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\DailyStartedLimitExceededException;
use App\Models\User;
use Tests\TestCase;

class DailyLimitExceededExceptionTest extends TestCase
{
    /**
     * limitKind() feeds details.limit_kind in the 403 response directly — a
     * swapped literal between the two classes would not be caught by static
     * analysis, since both return a plain string.
     */
    public function test_each_exception_names_its_own_limit_kind(): void
    {
        $user = $this->userWithId(1);

        $this->assertSame('started', DailyStartedLimitExceededException::exceededBy($user, TierEnum::FREE, 3)->limitKind());
        $this->assertSame('completed', DailyCompletedLimitExceededException::exceededBy($user, TierEnum::FREE, 1)->limitKind());
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
