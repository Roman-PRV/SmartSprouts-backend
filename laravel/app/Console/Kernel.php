<?php

namespace App\Console;

use App\Console\Commands\Entitlement\FlagFairUseOutliers;
use App\Console\Commands\Entitlement\PruneLevelDailyUsage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Off-peak: the prune deletes from the busiest table in the system.
        $schedule->command(PruneLevelDailyUsage::class)->dailyAt('03:00');
        $schedule->command(FlagFairUseOutliers::class)->dailyAt('03:15');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
