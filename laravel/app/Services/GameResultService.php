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
        try {
            $resultsArray = $results['results'] ?? [];

            $score = $this->calculateScore($resultsArray);
            $totalQuestions = count($resultsArray);

            GameResult::create([
                'user_id' => $dto->userId,
                'game_id' => $dto->game->id,
                'level_id' => $dto->levelId,
                'locale' => app()->getLocale(),
                'score' => $score,
                'total_questions' => $totalQuestions,
                'details' => $resultsArray,
            ]);
        } catch (\Throwable $e) {
            // We intentionally catch and log the exception without re-throwing it.
            // This ensures that a failure in saving the game result does not
            // prevent the user from seeing their score (graceful degradation).
            \Log::error('Failed to save game result: '.$e->getMessage(), [
                'game_id' => $dto->game->id,
                'level_id' => $dto->levelId,
                'exception' => $e,
            ]);
        }
    }

    private function calculateScore(array $results): int
    {
        return collect($results)->where('correct', true)->count();
    }
}
