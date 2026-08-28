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
     * Opens no transaction of its own — the opposite of recordOpen(). It must
     * run inside one the caller opens, because a refused completion has to
     * roll back the caller's own write (e.g. game_results) along with the mark.
     *
     * @return bool Whether a row was marked. False when there was nothing to
     *              mark: the level was already completed today, or no open
     *              was recorded for today at all.
     *
     * @throws DailyCompletedLimitExceededException
     */
    public function recordCompletion(User $user, TierEnum $tier, int $gameId, int $levelId): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('recordCompletion() must run inside the caller\'s transaction — its refusal has to roll back the caller\'s own write.');
        }

        $completedAt = now();
        $usageDate = $completedAt->copy()->startOfDay();

        $marked = $this->usageOn($user, $usageDate)
            ->where('game_id', $gameId)
            ->where('level_id', $levelId)
            ->whereNull('completed_at')
            ->update(['completed_at' => $completedAt]) > 0;

        $completedLimit = $tier->completedLimit();

        if (! $marked || $completedLimit === null) {
            return $marked;
        }

        // The row just marked is itself the completion being checked, so `>`
        // — the same reasoning as `started` in assertWithinAllowance().
        $completedToday = $this->usageOn($user, $usageDate)
            ->whereNotNull('completed_at')
            ->lockForUpdate()
            ->count();

        if ($completedToday > $completedLimit) {
            throw new DailyCompletedLimitExceededException(
                "User {$user->id} exceeded tier {$tier->value}'s daily completion limit of {$completedLimit}.",
            );
        }

        return true;
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
     * The count is a single locking read (FOR UPDATE): a plain COUNT wouldn't
     * see another transaction's uncommitted insert (research.md R3).
     *
     * @throws DailyStartedLimitExceededException
     * @throws DailyCompletedLimitExceededException
     */
    private function assertWithinAllowance(User $user, TierEnum $tier, Carbon $usageDate): void
    {
        $startedLimit = $tier->startedLimit();
        $completedLimit = $tier->completedLimit();

        if ($startedLimit === null && $completedLimit === null) {
            return;
        }

        $counts = $this->usageOn($user, $usageDate)
            ->toBase()
            ->selectRaw('count(*) as started, count(completed_at) as completed')
            ->lockForUpdate()
            ->first();

        if ($startedLimit !== null && (int) ($counts?->started ?? 0) > $startedLimit) {
            throw new DailyStartedLimitExceededException(
                "User {$user->id} exceeded tier {$tier->value}'s daily start limit of {$startedLimit}.",
            );
        }

        if ($completedLimit !== null && (int) ($counts?->completed ?? 0) >= $completedLimit) {
            throw new DailyCompletedLimitExceededException(
                "User {$user->id} exceeded tier {$tier->value}'s daily completion limit of {$completedLimit}.",
            );
        }
    }

    /**
     * @return Builder<LevelDailyUsage>
     */
    private function usageOn(User $user, Carbon $usageDate): Builder
    {
        return LevelDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', $usageDate->toDateString());
    }
}
