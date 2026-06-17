<?php

namespace App\Games\FindTheWrong\Services;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Records a player's attempt at a find-the-wrong level. Trusts the validated
 * partition of items (the request layer has already verified FK integrity and
 * full coverage); derives `score` as the count of `found` to match the
 * project's score-as-count convention (see GameResultService::calculateScore).
 *
 * `total_questions` is `count($found) + count($missedIds)` — equivalent to the
 * level's item count because the request validator enforced full coverage.
 */
class FindTheWrongAttemptService
{
    /**
     * @param  array<int, array{item_id: int, stars: int}>  $found
     * @param  array<int, int>  $missedIds
     * @param  string  $interactionMode  How the player played the level (circle|marker); recorded for analytics.
     */
    public function save(
        User $user,
        Game $game,
        FindTheWrongLevel $level,
        array $found,
        array $missedIds,
        int $durationSeconds,
        string $interactionMode,
    ): GameResult {
        return GameResult::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'level_id' => $level->id,
            'locale' => app()->getLocale(),
            'score' => count($found),
            'total_questions' => count($found) + count($missedIds),
            'details' => [
                'found' => array_map(
                    static fn (array $entry): array => [
                        'item_id' => (int) $entry['item_id'],
                        'stars' => (int) $entry['stars'],
                    ],
                    $found,
                ),
                'missed_item_ids' => $missedIds,
                'duration_seconds' => $durationSeconds,
                'interaction_mode' => $interactionMode,
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, FindTheWrongItem>
     */
    public function loadItems(array $ids): Collection
    {
        return FindTheWrongItem::whereIn('id', $ids)->get()->keyBy('id');
    }
}
