<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProfileAggregationService
{
    /**
     * Aggregate gameplay statistics for the given user.
     *
     * Statistics are calculated based on the LATEST attempt for each unique level:
     * - completed_levels: count of unique levels played (game_id + level_id)
     * - total_score:         sum of scores from the latest attempt of each level
     * - total_questions:  sum of total questions from the latest attempt of each level
     *
     * total_levels is computed by summing counts across each game's dynamic
     * level table (e.g. true_false_image_levels), since there is no single
     * unified levels table in this architecture.
     *
     * @return array{
     *     total_score: int,
     *     completed_levels: int,
     *     total_levels: int,
     *     correct_answers_percentage: float,
     * }
     */
    public function aggregate(User $user): array
    {
        $sub = GameResult::select('score', 'total_questions', DB::raw('ROW_NUMBER() OVER (PARTITION BY game_id, level_id ORDER BY created_at DESC) as rn'))
            ->where('user_id', $user->id);

        $aggregates = GameResult::fromSub($sub, 'sub')
            ->where('rn', 1)
            ->selectRaw('COUNT(*) as completed_levels, SUM(score) as total_score, SUM(total_questions) as total_questions')
            ->first();

        $totalScore = (int) ($aggregates?->total_score ?? 0);
        $completedLevels = (int) ($aggregates?->completed_levels ?? 0);
        $totalQuestions = (int) ($aggregates?->total_questions ?? 0);

        $allowedMap = ConfigHelper::getStringMap('game_services.map', []);

        $prefixes = Game::query()
            ->where('is_active', true)
            ->pluck('table_prefix')
            ->filter(fn ($prefix) => is_string($prefix) && isset($allowedMap[$prefix]));

        $totalLevels = 0;
        if ($prefixes->isNotEmpty()) {
            $cacheKey = 'global_total_levels_'.md5($prefixes->implode('-'));

            /** @var int|string $cachedLevels */
            $cachedLevels = Cache::remember($cacheKey, 3600, function () use ($prefixes): int {
                $validPrefixes = [];
                foreach ($prefixes as $prefix) {
                    $tableName = "{$prefix}_levels";
                    if (Schema::hasTable($tableName)) {
                        $validPrefixes[] = $prefix;
                    } else {
                        Log::warning("Game table missing for active game prefix: {$prefix}. Aggregation will skip this table.");
                    }
                }

                if (empty($validPrefixes)) {
                    return 0;
                }

                $subQueries = collect($validPrefixes)->map(function ($prefix) {
                    return 'SELECT COUNT(*) as cnt FROM '.$prefix.'_levels';
                })->implode(' UNION ALL ');

                return (int) DB::table(DB::raw("($subQueries) as sub"))->sum('cnt');
            });

            $totalLevels = (int) $cachedLevels;
        }

        return [
            'total_score' => $totalScore,
            'completed_levels' => $completedLevels,
            'total_levels' => $totalLevels,
            'correct_answers_percentage' => $totalQuestions > 0
                ? round($totalScore / $totalQuestions * 100, 2)
                : 0.0,
        ];
    }
}
