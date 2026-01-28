<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameResult;

class GameResultService
{
    /**
     * Save game result for authenticated user.
     */
    public function save(Game $game, int $levelId, array $results): void
    {
        $resultsArray = $results['results'] ?? [];

        $score = $this->calculateScore($resultsArray);
        $totalQuestions = count($resultsArray);

        GameResult::create([
            'user_id' => auth()->id(),
            'game_id' => $game->id,
            'level_id' => $levelId,
            'locale' => app()->getLocale(),
            'score' => $score,
            'total_questions' => $totalQuestions,
            'details' => $resultsArray,
        ]);
    }

    private function calculateScore(array $results): int
    {
        return collect($results)->where('correct', true)->count();
    }
}
