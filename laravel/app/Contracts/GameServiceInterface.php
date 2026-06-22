<?php

namespace App\Contracts;

use App\Models\Game;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface GameServiceInterface
{
    public function fetchAllLevels(): Collection;

    public function fetchLevel(int $levelId): Level;

    /**
     * Validate a player's raw submission for a level, score it, persist the
     * result and return the response body. Each game owns its own payload shape,
     * scoring and response — the controller is a thin dispatcher.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function submit(User $user, Game $game, int $levelId, array $payload): array;
}
