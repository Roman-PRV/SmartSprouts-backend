<?php

namespace App\Games\TrueFalseImage\Services;

use App\Contracts\GameServiceInterface;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TrueFalseImageService implements GameServiceInterface
{
    /**
     * Fetch all levels for the game (no statements attached). Throws LevelsTableMissingException when table is absent.
     */
    public function fetchAllLevels(): Collection
    {
        return TrueFalseImageLevel::all();
    }

    /**
     * Fetch a Level row by id.
     */
    public function fetchLevel(int $levelId): ?Level
    {
        return TrueFalseImageLevel::with('statements')->find($levelId);
    }

    /**
     * Fetch statements for a given level id.
     *
     * @throws InvalidArgumentException
     */
    public function fetchDataForLevel(int $levelId): Collection
    {
        return TrueFalseImageStatement::where('level_id', $levelId)->get();
    }
}
