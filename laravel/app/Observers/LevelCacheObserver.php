<?php

namespace App\Observers;

use App\Services\ProfileAggregationService;
use Illuminate\Support\Facades\Cache;

/**
 * Busts the cached global total-levels count whenever a level row is created or
 * deleted in any game, so the profile "X of Y levels" stat can't stay stale for
 * up to the cache TTL. Registered per level model in EventServiceProvider; the
 * flush is model-agnostic (the count spans every game's levels table).
 */
final class LevelCacheObserver
{
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
