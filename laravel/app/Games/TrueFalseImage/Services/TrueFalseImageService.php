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

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        return TrueFalseImageLevel::all();
    }

    /**
     * Fetch a Level row by id.
     *
     * @throws TableMissingException
     * @throws InvalidArgumentException
     */
    public function fetchLevel(int $levelId): Level
    {
        $table = (new TrueFalseImageLevel)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $level = TrueFalseImageLevel::with('statements')->find($levelId);

        if (! $level) {
            throw new InvalidArgumentException("Level {$levelId} not found in {$table}");
        }

        if ($level->statements === null) {
            $statementsTable = (new TrueFalseImageStatement)->getTable();
            if (! Schema::hasTable($statementsTable)) {
                throw new TableMissingException($statementsTable);
            }
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

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        $statements = TrueFalseImageStatement::where('level_id', $levelId)->get();

        if ($statements->isEmpty()) {
            throw new InvalidArgumentException("No statements found for level {$levelId} in {$table}");
        }

        return $statements;
    }
}
