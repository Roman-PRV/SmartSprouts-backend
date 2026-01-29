<?php

namespace App\DTO;

use App\Models\Game;

class CheckAnswersDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly Game $game,
        public readonly int $levelId,
        public readonly array $answers,
    ) {}
}
