<?php

namespace App\Games\TrueFalseImage\Services;

use App\DTO\CheckAnswersDTO;
use App\Exceptions\TableMissingException;
use App\Games\TrueFalse\Services\StatementGameService;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Helpers\MediaHelper;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrueFalseImageService extends StatementGameService
{
    /**
     * Fetch all levels for the game (no statements attached).
     *
     * @throws TableMissingException
     */
    public function fetchAllLevels(): Collection
    {
        $table = (new TrueFalseImageLevel)->getTable();

        if (! Schema::hasTable($table)) {
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

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseImageLevel::with('statements')->find($levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        $statementsTable = (new TrueFalseImageStatement)->getTable();
        if (! Schema::hasTable($statementsTable)) {
            throw new TableMissingException($statementsTable);
        }

        return $level;
    }

    /**
     * Check player answers for a level.
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function check(CheckAnswersDTO $dto): array
    {
        $table = (new TrueFalseImageStatement)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseImageLevel::with('statements')->find($dto->levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$dto->levelId} not found");
        }

        $statements = $level->statements;

        // Validate all statement_ids before processing
        $statementIds = array_column($dto->answers, 'statement_id');
        $existingStatementIds = $statements->pluck('id')->all();

        foreach ($statementIds as $statementId) {
            if (! in_array($statementId, $existingStatementIds, true)) {
                throw ValidationException::withMessages([
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
                'statement_audio_url' => MediaHelper::getAttributeUrl($statement, 'statement_audio_url'),
                'explanation_audio_url' => MediaHelper::getAttributeUrl($statement, 'explanation_audio_url'),
            ];
        }

        return ['results' => $results];
    }
}
