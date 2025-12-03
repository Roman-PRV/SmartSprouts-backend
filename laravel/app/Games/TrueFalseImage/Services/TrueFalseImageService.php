<?php

namespace App\Games\TrueFalseImage\Services;

use App\Contracts\GameServiceInterface;
use App\Exceptions\TableMissingException;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrueFalseImageService implements GameServiceInterface
{
    /**
     * Fetch all levels for the game (no statements attached).
     *
     * @throws TableMissingException
     */
    public function fetchAllLevels(): Collection
    {
        $table = (new TrueFalseImageLevel)->getTable();

        if (!Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        return TrueFalseImageLevel::all();
    }

    /**
     * Fetch a Level row by id.
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     */
    public function fetchLevel(int $levelId): Level
    {
        $table = (new TrueFalseImageLevel)->getTable();

        if (!Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseImageLevel::with('statements')->find($levelId);

        if (!$level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        $statementsTable = (new TrueFalseImageStatement)->getTable();
        if (!Schema::hasTable($statementsTable)) {
            throw new TableMissingException($statementsTable);
        }

        return $level;
    }

    /**
     * Fetch statements for a given level id.
     *
     * @throws TableMissingException
     * @throws InvalidArgumentException
     */
    public function fetchDataForLevel(int $levelId): Collection
    {
        $table = (new TrueFalseImageStatement)->getTable();

        if (!Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $statements = TrueFalseImageStatement::where('level_id', $levelId)->get();

        if ($statements->isEmpty()) {
            throw new InvalidArgumentException("No statements found for level {$levelId}");
        }

        return $statements;
    }

    /**
     * Check player answers for a level.
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     */
    public function check(int $levelId, array $payload): array
    {
        $table = (new TrueFalseImageStatement)->getTable();

        if (!Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $results = [];

        foreach ($payload['answers'] as $answer) {
            $statementId = $answer['statement_id'];
            $playerAnswer = $answer['answer'];

            /** @var TrueFalseImageStatement|null $statement */
            $statement = TrueFalseImageStatement::find($statementId);

            if (!$statement) {
                throw new NotFoundHttpException("Statement {$statementId} not found");
            }

            $correct = $playerAnswer === $statement->is_true;

            $results[] = [
                'statement_id' => $statementId,
                'correct' => $correct,
                'is_true' => $statement->is_true,
                'explanation' => $statement->explanation,
            ];
        }

        return ['results' => $results];
    }
}
