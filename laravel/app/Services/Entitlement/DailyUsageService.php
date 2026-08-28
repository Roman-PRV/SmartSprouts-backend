<?php

namespace App\Services\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\DailyStartedLimitExceededException;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Counts today's distinct level opens and completions and enforces the two
 * daily allowances.
 */
class DailyUsageService
{
    /**
     * Inserts the row before counting, under the same transaction — counting
     * first would be a check-then-act race between two devices (research.md R3).
     *
     * @return bool Whether this was a new open. False is a free replay.
     *
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    public function recordOpen(User $user, TierEnum $tier, int $gameId, int $levelId): bool
    {
        // One clock read: usage_date and opened_at must land on the same side of midnight.
        $openedAt = now();
        $usageDate = $openedAt->copy()->startOfDay();

        return DB::transaction(function () use ($user, $tier, $gameId, $levelId, $openedAt, $usageDate): bool {
            $isNew = $this->insert($user, $gameId, $levelId, $openedAt);

            if ($isNew) {
                $this->assertWithinAllowance($user, $tier, $usageDate);
            }

            return $isNew;
        }, 3); // 3 attempts: the locking read below can deadlock and needs a retry.
    }

    /**
     * Marks completion on the row opened earlier today. Idempotent: a row
     * already completed is left untouched, so a replayed completion never
     * counts twice — completing is never gated or re-checked (FR-005).
     *
     * @return bool Whether a row was marked. False means no open was recorded
     *              for today — e.g. the level was opened just before the UTC
     *              boundary and completed just after it.
     */
    public function recordCompletion(User $user, int $gameId, int $levelId): bool
    {
        return LevelDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', today())
            ->where('game_id', $gameId)
            ->where('level_id', $levelId)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]) > 0;
    }

    /**
     * @return bool False when the unique key already existed — a replay.
     */
    private function insert(User $user, int $gameId, int $levelId, Carbon $openedAt): bool
    {
        try {
            LevelDailyUsage::query()->create([
                'user_id' => $user->id,
                'usage_date' => $openedAt->toDateString(),
                'game_id' => $gameId,
                'level_id' => $levelId,
                'opened_at' => $openedAt,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * The row just inserted is itself the start being checked, so `started`
     * compares with `>`; `completed` is unaffected by this insert and
     * compares with `>=`.
     *
     * Both counts are locking reads: a plain COUNT is a consistent snapshot
     * that never sees another transaction's uncommitted insert, so two
     * different levels opened at the same instant would both count "under
     * the limit" and both commit. Locking the range forces the second
     * transaction to wait for the first to finish before it counts.
     *
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    private function assertWithinAllowance(User $user, TierEnum $tier, Carbon $usageDate): void
    {
        $startedLimit = $tier->startedLimit();

        if ($startedLimit !== null && $this->todayUsage($user, $usageDate)->lockForUpdate()->count() > $startedLimit) {
            throw new DailyStartedLimitExceededException(
                "User {$user->id} exceeded tier {$tier->value}'s daily start limit of {$startedLimit}.",
            );
        }

        $completedLimit = $tier->completedLimit();

        if ($completedLimit !== null && $this->todayUsage($user, $usageDate)->whereNotNull('completed_at')->lockForUpdate()->count() >= $completedLimit) {
            throw new DailyCompletedLimitExceededException(
                "User {$user->id} exceeded tier {$tier->value}'s daily completion limit of {$completedLimit}.",
            );
        }
    }

    /**
     * $usageDate must be the start of the day, not a live timestamp: the
     * `date` cast stores usage_date as 'Y-m-d 00:00:00', and a DateTimeInterface
     * binding is compared against that exact string, time component included.
     *
     * @return Builder<LevelDailyUsage>
     */
    private function todayUsage(User $user, Carbon $usageDate): Builder
    {
        return LevelDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', $usageDate);
    }
}
