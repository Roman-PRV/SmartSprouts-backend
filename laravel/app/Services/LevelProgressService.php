<?php

namespace App\Services;

use App\Enums\LevelProgressStatus;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class LevelProgressService
{
    /**
     * Decorate each level with the player's progress, read from their most recent
     * attempt per level. Fetches only the latest attempt per level via a MAX(id)
     * subquery (≤ N rows regardless of how often the player replayed), then writes
     * the result into a virtual `progress` attribute consumed by LevelDescriptionResource.
     *
     * The decorated models are for serialization only — `progress` is not a column,
     * so they must not be persisted (a `save()` would try to write a missing column).
     *
     * @param  Collection<int, Level>  $levels
     */
    public function decorate(User $user, Game $game, Collection $levels): void
    {
        $levelIds = $levels->pluck('id')->all();

        if ($levelIds === []) {
            return;
        }

        // id is auto-increment and monotonic, so MAX(id) per level is the last
        // inserted attempt (more reliable than created_at on same-second ties).
        $latestIds = GameResult::query()
            ->where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->whereIn('level_id', $levelIds)
            ->groupBy('level_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        $latestPerLevel = GameResult::query()
            ->whereIn('id', $latestIds)
            ->get(['level_id', 'score', 'total_questions'])
            ->keyBy('level_id');

        foreach ($levels as $level) {
            $result = $latestPerLevel->get($level->id);

            $status = $result === null
                ? LevelProgressStatus::NOT_STARTED
                : LevelProgressStatus::fromScore((int) $result->score, (int) $result->total_questions);

            $level->setAttribute('progress', $status->value);
        }
    }
}
