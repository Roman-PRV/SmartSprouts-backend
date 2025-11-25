<?php

namespace App\Contracts;

use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;

interface GameServiceInterface
{
    public function fetchAllLevels(): Collection;

    public function fetchLevel(int $levelId): Level;

    public function fetchDataForLevel(int $levelId): Collection;
}
