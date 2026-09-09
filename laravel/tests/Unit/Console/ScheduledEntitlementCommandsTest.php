<?php

namespace Tests\Unit\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Nothing else runs these commands: schedule:work only invokes what the
 * Kernel registers, so a change here silently stops the whole feature and
 * nothing else in the suite would notice.
 */
class ScheduledEntitlementCommandsTest extends TestCase
{
    /** @test */
    public function it_schedules_both_maintenance_commands_daily(): void
    {
        $expressions = collect($this->app->make(Schedule::class)->events())
            ->mapWithKeys(fn (Event $event): array => [$event->command => $event->expression]);

        $this->assertSame('0 3 * * *', $expressions->first(
            fn (string $expression, string $command): bool => str_contains($command, 'entitlement:prune-usage'),
        ));
        $this->assertSame('15 3 * * *', $expressions->first(
            fn (string $expression, string $command): bool => str_contains($command, 'entitlement:flag-fair-use'),
        ));
    }
}
