<?php

namespace Tests\Feature\Entitlement\Concerns;

use App\Games\Arithmetic\Support\ArithmeticConstants;
use App\Models\Game;

/**
 * Both gates are game-agnostic, so these tests drive them through the
 * arithmetic game: its levels are synthesized, so there is no per-game content
 * to seed before a request can reach the gate.
 */
trait UsesArithmeticGate
{
    private function arithmeticGame(): Game
    {
        return Game::factory()->create([
            'key' => 'multiplication_table',
            'table_prefix' => 'multiplication_table',
        ]);
    }

    private function openUrl(Game $game, int $level): string
    {
        return "/api/games/{$game->id}/levels/{$level}";
    }

    private function submitUrl(Game $game, int $level): string
    {
        return "/api/games/{$game->id}/levels/{$level}/attempts";
    }

    /**
     * @return array{answers: array<int, array{equation_id: int, answer: int}>}
     */
    private function correctPayloadFor(int $level): array
    {
        $answers = [];

        for ($b = 1; $b <= ArithmeticConstants::FACTS_PER_LEVEL; $b++) {
            $answers[] = ['equation_id' => $b, 'answer' => $level * $b];
        }

        return ['answers' => $answers];
    }
}
