<?php

namespace App\Games\TrueFalseText\Services;

use App\Contracts\GameServiceInterface;
use App\DTO\CheckAnswersDTO;
use App\Exceptions\TableMissingException;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrueFalseTextService implements GameServiceInterface
{
    /**
     * Fetch all levels for the game (no statements attached).
     *
     * @throws TableMissingException
     */
    public function fetchAllLevels(): Collection
    {
        $table = (new TrueFalseTextLevel)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        return TrueFalseTextLevel::all();
    }

    /**
     * Fetch a Level row by id.
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     */
    public function fetchLevel(int $levelId): Level
    {
        $table = (new TrueFalseTextLevel)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseTextLevel::with('statements')->find($levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        $statementsTable = (new TrueFalseTextStatement)->getTable();
        if (! Schema::hasTable($statementsTable)) {
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
        $table = (new TrueFalseTextStatement)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $statements = TrueFalseTextStatement::where('level_id', $levelId)->get();

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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function check(CheckAnswersDTO $dto): array
    {
        $table = (new TrueFalseTextStatement)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseTextLevel::with('statements')->find($dto->levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$dto->levelId} not found");
        }

        $statements = $level->statements;

        // Validate all statement_ids before processing
        $statementIds = array_column($dto->answers, 'statement_id');
        $existingStatementIds = $statements->pluck('id')->all();

        foreach ($statementIds as $statementId) {
            if (! in_array($statementId, $existingStatementIds, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'answers' => ["The statement {$statementId} does not belong to level {$dto->levelId}."],
                ]);
            }
        }

        $results = [];

        foreach ($dto->answers as $answer) {
            $statementId = $answer['statement_id'];
            $playerAnswer = $answer['answer'];

            $statement = $statements->where('id', $statementId)->first();

            // This should never be null because we validated above, but PHPStan doesn't know that
            assert($statement !== null, 'Statement must exist after validation');

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
