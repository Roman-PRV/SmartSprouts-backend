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
 * daily allowances. Implements the insert-then-count transaction from
 * data-model.md (R3): counting before inserting would let two devices both
 * read "under the limit" and both proceed.
 */
class DailyUsageService
{
    /**
     * Records the open unconditionally, then checks both allowances under the
     * same transaction and rolls back the whole thing on either refusal. A
     * tier with no daily limits never rolls back: assertWithinAllowance reads
     * a null limit as "not enforced" and skips straight past it, so recording
     * still happens — this table is the only source for the fair-use report
     * (FR-007a) for exactly that tier.
     *
     * Runs up to three attempts: the allowance check below takes a locking
     * read taken *after* the insert, so when the same account really does
     * open two different levels at the same instant, each transaction holds
     * its own new row and waits for the other's — a deadlock, which MySQL
     * breaks by killing one. The retry re-runs it against the committed
     * state and gets the right answer. Not a precaution: without it every
     * genuine race is a 500.
     *
     * @return bool Whether this was a new open. False is a free replay.
     *
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    public function recordOpen(User $user, TierEnum $tier, int $gameId, int $levelId): bool
    {
        // Read once and pass it down: usage_date and opened_at must land on
        // the same side of midnight as each other, and as the count taken
        // below, or a row opened right at the boundary could be dated
        // yesterday while counted as today's (or vice versa) and slip past
        // the limit uncounted.
        $openedAt = now();

        return DB::transaction(function () use ($user, $tier, $gameId, $levelId, $openedAt) {
            $isNew = $this->insert($user, $gameId, $levelId, $openedAt);

            if ($isNew) {
                $this->assertWithinAllowance($user, $tier, $openedAt->toDateString());
            }

            return $isNew;
        }, 3);
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
            ->whereDate('usage_date', today())
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
    private function assertWithinAllowance(User $user, TierEnum $tier, string $usageDate): void
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
     * whereDate(), not where(): the `date` cast serialises usage_date with a
     * time component for storage, so a plain string equality would silently
     * match nothing against a bare 'Y-m-d' value.
     *
     * @return Builder<LevelDailyUsage>
     */
    private function todayUsage(User $user, string $usageDate): Builder
    {
        return LevelDailyUsage::query()
            ->where('user_id', $user->id)
            ->whereDate('usage_date', $usageDate);
    }
}
