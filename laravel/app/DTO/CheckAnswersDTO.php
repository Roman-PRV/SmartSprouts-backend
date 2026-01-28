<?php

namespace App\DTO;

use App\Models\Game;

class CheckAnswersDTO
{
    public function __construct(
        public int $userId,
        public Game $game,
        public int $levelId,
        public array $answers,
    ) {}
}
