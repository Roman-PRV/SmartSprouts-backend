<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfileAggregationService
{
    /**
     * Aggregate gameplay statistics for the given user.
     *
     * Statistics are calculated based on the LATEST attempt for each unique level:
     * - completed_levels: count of unique levels played (game_id + level_id)
     * - total_xp:         sum of scores from the latest attempt of each level
     * - total_questions:  sum of total questions from the latest attempt of each level
     *
     * total_levels is computed by summing counts across each game's dynamic
     * level table (e.g. true_false_image_levels), since there is no single
     * unified levels table in this architecture.
     *
     * @return array{
     *     total_xp: int,
     *     completed_levels: int,
     *     total_levels: int,
     *     correct_answers_percentage: float,
     * }
     */
    public function aggregate(User $user): array
    {
        $latestResults = $user->gameResults()
            ->latest()
            ->get()
            ->unique(fn ($result) => "{$result->game_id}-{$result->level_id}");

        $completedLevels = $latestResults->count();

        $totalXp = $latestResults->sum('score');
        $totalXp = is_numeric($totalXp) ? (int) $totalXp : 0;

        $totalQuestions = $latestResults->sum('total_questions');
        $totalQuestions = is_numeric($totalQuestions) ? (int) $totalQuestions : 0;

        $allowedMap = ConfigHelper::getStringMap('game_services.map', []);

        $totalLevels = Game::query()
            ->where('is_active', true)
            ->get(['table_prefix'])
            ->reduce(
                function (int $carry, Game $game) use ($allowedMap): int {
                    $prefix = $game->table_prefix;

                    // Validate prefix against known game services map before using in DB table name (Hardening)
                    if (! $prefix || ! isset($allowedMap[$prefix])) {
                        return $carry;
                    }

                    return $carry + (int) DB::table("{$prefix}_levels")->count();
                },
                0
            );

        return [
            'total_xp' => $totalXp,
            'completed_levels' => $completedLevels,
            'total_levels' => $totalLevels,
            'correct_answers_percentage' => $totalQuestions > 0
                ? round($totalXp / $totalQuestions * 100, 2)
                : 0.0,
        ];
    }
}
