<?php

namespace App\Services;

use App\DTO\CheckAnswersDTO;
use App\Models\GameResult;

class GameResultService
{
    /**
     * Save game result for authenticated user.
     *
     * @param  array{results?: array<int, array{correct: bool, ...}>}  $results  Scored per-question results; only `correct` is read, the rest is stored as-is in `details`.
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

    /**
     * Count the questions answered correctly.
     *
     * @param  array<int, array{correct: bool, ...}>  $results
     */
    private function calculateScore(array $results): int
    {
        return collect($results)->where('correct', true)->count();
    }
}
