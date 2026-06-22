<?php

namespace App\Contracts;

use App\DTO\CheckAnswersDTO;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;

interface GameServiceInterface
{
    public function fetchAllLevels(): Collection;

    public function fetchLevel(int $levelId): Level;

    public function check(CheckAnswersDTO $dto): array;
}
