<?php

namespace App\Games\TrueFalse\Services;

use App\Contracts\GameServiceInterface;
use App\DTO\CheckAnswersDTO;
use App\Models\Game;
use App\Models\User;
use App\Services\GameResultService;
use Illuminate\Support\Facades\Validator;

/**
 * Shared engine for the statement-based true/false games (image + text). They
 * are mechanically identical and differ only in their backing models, so the
 * submit flow (validate → score → persist) lives here once. Each game supplies
 * its own reads and scoring via fetchAllLevels()/fetchLevel()/check(), which
 * touch the game-specific models.
 */
abstract class StatementGameService implements GameServiceInterface
{
    public function __construct(private GameResultService $gameResults) {}

    /**
     * Validate the player's answers, score them and persist the result.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function submit(User $user, Game $game, int $levelId, array $payload): array
    {
        Validator::make($payload, $this->submitRules())->validate();

        $dto = new CheckAnswersDTO($user->id, $game, $levelId, $payload['answers']);
        $results = $this->check($dto);
        $this->gameResults->save($dto, $results);

        return $results;
    }

    /**
     * Score a level's answers (no persistence). Game-specific: it reads the
     * concrete statement model.
     *
     * @return array<string, mixed>
     */
    abstract public function check(CheckAnswersDTO $dto): array;

    /**
     * Validation rules for the submit payload (boolean answer per statement).
     *
     * @return array<string, string>
     */
    protected function submitRules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.*.statement_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
        ];
    }
}
