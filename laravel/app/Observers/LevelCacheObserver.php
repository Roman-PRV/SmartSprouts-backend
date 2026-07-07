<?php

namespace App\Observers;

use App\Services\ProfileAggregationService;
use Illuminate\Support\Facades\Cache;

/**
 * Busts the cached global total-levels count whenever a level row is created or
 * deleted in any game, so the profile "X of Y levels" stat can't stay stale for
 * up to the cache TTL. The flush is model-agnostic (the count spans every game's
 * levels table).
 *
 * Registered per level model in EventServiceProvider — when adding a new game
 * with its own levels table, register its Level model there too.
 */
final class LevelCacheObserver
{
    /**
     * Defer the flush until a surrounding transaction commits (runs immediately
     * when there is none), so a concurrent read can't re-cache the pre-commit
     * count.
     */
    public bool $afterCommit = true;

    public function created(): void
    {
        $this->flush();
    }

    public function deleted(): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::forget(ProfileAggregationService::totalLevelsCacheKey());
    }
}
