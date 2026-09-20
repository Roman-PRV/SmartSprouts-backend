<?php

namespace App\Console\Commands\Entitlement;

use App\Helpers\ConfigHelper;
use App\Models\Entitlement\LevelDailyUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Lists accounts whose level opens on a single day crossed the fair-use
 * threshold. Reports and nothing else — acting on one is a human decision.
 *
 * No tier filter: the threshold sits far above every metered tier's own start
 * allowance, so an account that reaches it is already unlimited or exempt.
 *
 * Every outcome is logged, not only printed: the scheduler discards a task's
 * console output.
 */
class FlagFairUseOutliers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entitlement:flag-fair-use
                            {--date= : The day to report on as Y-m-d. Defaults to yesterday.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report accounts whose daily level opens crossed the fair-use review threshold';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = ConfigHelper::getInt('billing.fair_use_review_threshold');

        // Refused rather than defaulted: at zero every account is listed and
        // the signal stops being a signal.
        if ($threshold < 1) {
            $message = "billing.fair_use_review_threshold must be a positive integer, got {$threshold}. Check BILLING_FAIR_USE_REVIEW_THRESHOLD — an empty or non-numeric value becomes 0.";

            Log::error($message, ['command' => $this->getName()]);
            $this->error($message);

            return self::FAILURE;
        }

        $day = $this->requestedDay();

        if ($day === null) {
            return self::FAILURE;
        }

        $date = $day->toDateString();

        $outliers = LevelDailyUsage::query()
            ->toBase()
            ->select('user_id')
            ->selectRaw('count(*) as opens')
            ->where('usage_date', $date)
            ->groupBy('user_id')
            ->having('opens', '>', $threshold)
            ->orderByDesc('opens')
            ->get();

        if ($outliers->isEmpty()) {
            return $this->reportNothingFound($date, $threshold);
        }

        foreach ($outliers as $outlier) {
            Log::warning('Fair-use review candidate', [
                'user_id' => $outlier->user_id,
                'usage_date' => $date,
                'opens' => $outlier->opens,
                'threshold' => $threshold,
            ]);
        }

        $this->warn("{$outliers->count()} account(s) opened more than {$threshold} levels on {$date}:");
        $this->table(
            ['User', 'Level opens'],
            $outliers->map(fn (object $outlier): array => [$outlier->user_id, $outlier->opens])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * A quiet day and a day the prune has already swept both produce no
     * outliers, and only the rows themselves tell them apart — so this asks
     * the table rather than counting back from today's date.
     */
    private function reportNothingFound(string $date, int $threshold): int
    {
        $recorded = LevelDailyUsage::query()->where('usage_date', $date)->exists();

        $message = $recorded
            ? "No account opened more than {$threshold} levels on {$date}."
            : "No usage was recorded on {$date} — those rows may already have been pruned.";

        Log::info('Fair-use report found no candidates', [
            'usage_date' => $date,
            'threshold' => $threshold,
            'rows_recorded' => $recorded,
        ]);
        $this->info($message);

        return self::SUCCESS;
    }

    /**
     * The day to report on, or null once the refusal has been printed.
     *
     * Yesterday by default: the scheduler fires while today is still being
     * played, and a partial day cannot be compared against a daily figure.
     */
    private function requestedDay(): ?Carbon
    {
        $option = $this->option('date');

        if (! is_string($option) || $option === '') {
            return now()->subDay();
        }

        // hasFormat accepts an impossible day — 2026-02-31 parses forward to
        // March 3 — so the round-trip is what rules it out.
        $day = Carbon::hasFormat($option, 'Y-m-d') ? Carbon::parse($option) : null;

        if ($day === null || $day->toDateString() !== $option) {
            $this->error("--date must be a real day written as Y-m-d, got \"{$option}\".");

            return null;
        }

        if ($day->isFuture()) {
            $this->error("--date cannot be a day that has not happened yet, got \"{$option}\".");

            return null;
        }

        return $day;
    }
}
