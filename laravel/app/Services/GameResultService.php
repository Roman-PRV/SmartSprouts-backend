<?php

namespace App\Services;

use App\DTO\CheckAnswersDTO;
use App\Models\GameResult;

class GameResultService
{
    /**
     * Save game result for authenticated user.
     */
    public function save(CheckAnswersDTO $dto, array $results): void
    {
        $resultsArray = $results['results'] ?? [];

        GameResult::create([
            'user_id' => $dto->userId,
            'game_id' => $dto->game->id,
            'level_id' => $dto->levelId,
            'locale' => app()->getLocale(),
            'score' => $this->calculateScore($resultsArray),
            'total_questions' => count($resultsArray),
            'details' => $resultsArray,
        ]);
    }

    private function calculateScore(array $results): int
    {
        return collect($results)->where('correct', true)->count();
    }
}
