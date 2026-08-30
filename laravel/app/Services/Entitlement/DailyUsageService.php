<?php

namespace App\Services\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\DailyStartedLimitExceededException;
use App\Exceptions\Entitlement\LevelNotOpenedTodayException;
use App\Models\Entitlement\LevelDailyUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

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
     * @throws LogicException
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    public function recordOpen(User $user, TierEnum $tier, int $gameId, int $levelId): bool
    {
        if (DB::transactionLevel() > 0) {
            throw new LogicException('recordOpen() must not run inside an outer transaction — the deadlock retry it relies on is skipped when nested.');
        }

        // One clock read: usage_date and opened_at must land on the same side of midnight.
        $openedAt = now();
        $usageDate = $openedAt->toDateString();

        return DB::transaction(function () use ($user, $tier, $gameId, $levelId, $openedAt, $usageDate): bool {
            $isNew = $this->insert($user, $gameId, $levelId, $openedAt);

            if ($isNew) {
                $this->assertWithinAllowance($user, $tier, $usageDate);
            }

            return $isNew;
        }, 3); // 3 attempts: the locking read below can deadlock and needs a retry.
    }

    /**
     * Opens no transaction of its own — the opposite of recordOpen(). It must
     * run inside one the caller opens, because a refused completion has to
     * roll back the caller's own write (e.g. game_results) along with the mark.
     * The caller's transaction must carry the deadlock retry — the two locks
     * below can deadlock exactly as recordOpen()'s do.
     *
     * A level opened before midnight has no row for "today" once the clock
     * turns, so it throws LevelNotOpenedTodayException here too — deliberate
     * for now (DailyResetTest), not an oversight.
     *
     * @return bool Whether a row was marked. False means the level was
     *              already completed today — a free replay.
     *
     * @throws LogicException
     * @throws LevelNotOpenedTodayException
     * @throws DailyCompletedLimitExceededException
     */
    public function recordCompletion(User $user, TierEnum $tier, int $gameId, int $levelId): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('recordCompletion() must run inside the caller\'s transaction — its refusal has to roll back the caller\'s own write.');
        }

        $completedAt = now();
        $usageDate = $completedAt->toDateString();

        $usage = $this->usageOn($user, $usageDate)
            ->where('game_id', $gameId)
            ->where('level_id', $levelId);

        // whereNull() keeps this atomic regardless of locking — 0 rows means
        // either a replay or no row at all, checked below.
        $marked = (clone $usage)->whereNull('completed_at')->update(['completed_at' => $completedAt]) > 0;

        if (! $marked) {
            // A count against an absent row and a count under the limit look
            // identical, so existence is checked directly. The lock matters
            // only here: without it, a concurrent insert not yet visible would
            // read as "never opened".
            if (! $usage->lockForUpdate()->exists()) {
                throw new LevelNotOpenedTodayException(
                    "User {$user->id} submitted level {$levelId} without opening it today.",
                );
            }

            return false;
        }

        $completedLimit = $tier->completedLimit();

        if ($completedLimit === null) {
            return true;
        }

        // The row just marked is itself the completion being checked, so `>`
        // — the same reasoning as `started` in assertWithinAllowance().
        if ($this->countsToday($user, $usageDate, locking: true)['completed'] > $completedLimit) {
            throw DailyCompletedLimitExceededException::exceededBy($user, $tier, $completedLimit);
        }

        return true;
    }

    /**
     * $locking is for enforcement callers only (research.md R3) — a display
     * read (GET /api/entitlement) should leave it false.
     *
     * @return array{started: int, completed: int}
     */
    public function countsToday(User $user, ?string $usageDate = null, bool $locking = false): array
    {
        $query = $this->usageOn($user, $usageDate ?? now()->toDateString())
            ->toBase()
            ->selectRaw('count(*) as started, count(completed_at) as completed');

        if ($locking) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return [
            'started' => (int) ($row?->started ?? 0),
            'completed' => (int) ($row?->completed ?? 0),
        ];
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
     * `started` compares with `>` since the row just inserted counts as the
     * start being checked; `completed` is unaffected by that insert, so `>=`.
     *
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    private function assertWithinAllowance(User $user, TierEnum $tier, string $usageDate): void
    {
        $startedLimit = $tier->startedLimit();
        $completedLimit = $tier->completedLimit();

        if ($startedLimit === null && $completedLimit === null) {
            return;
        }

        $counts = $this->countsToday($user, $usageDate, locking: true);

        if ($startedLimit !== null && $counts['started'] > $startedLimit) {
            throw DailyStartedLimitExceededException::exceededBy($user, $tier, $startedLimit);
        }

        if ($completedLimit !== null && $counts['completed'] >= $completedLimit) {
            throw DailyCompletedLimitExceededException::exceededBy($user, $tier, $completedLimit);
        }
    }

    /**
     * @return Builder<LevelDailyUsage>
     */
    private function usageOn(User $user, string $usageDate): Builder
    {
        return LevelDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', $usageDate);
    }
}
