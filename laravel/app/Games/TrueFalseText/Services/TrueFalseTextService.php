<?php

namespace App\Games\TrueFalseText\Services;

use App\Contracts\GameServiceInterface;
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
            throw new NotFoundHttpException("Level {$levelId} not found in {$table}");
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
            throw new InvalidArgumentException("No statements found for level {$levelId} in {$table}");
        }

        return $statements;
    }
}
