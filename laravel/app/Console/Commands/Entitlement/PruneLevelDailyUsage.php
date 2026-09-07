<?php

namespace App\Console\Commands\Entitlement;

use App\Helpers\ConfigHelper;
use App\Models\Entitlement\LevelDailyUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sweeps level_daily_usage down to the configured retention window.
 *
 * Every outcome is logged, not only printed: the scheduler discards a task's
 * console output.
 */
class PruneLevelDailyUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entitlement:prune-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete daily usage rows older than the configured retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = ConfigHelper::getInt('billing.usage_retention_days');

        // Refused rather than defaulted: at zero this deletes the history it
        // exists to keep, today's included.
        if ($days < 1) {
            $message = "billing.usage_retention_days must be a positive integer, got {$days}. Check BILLING_USAGE_RETENTION_DAYS — an empty or non-numeric value becomes 0.";

            Log::error($message, ['command' => $this->getName()]);
            $this->error($message);

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days)->toDateString();

        $deleted = (int) LevelDailyUsage::query()
            ->where('usage_date', '<', $cutoff)
            ->delete();

        Log::info('Pruned daily usage rows', ['deleted' => $deleted, 'before' => $cutoff]);
        $this->info("Deleted {$deleted} usage rows dated before {$cutoff}.");

        return self::SUCCESS;
    }
}
