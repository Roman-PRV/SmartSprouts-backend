<?php

namespace App\Contracts;

use App\Models\Game;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface GameServiceInterface
{
    public function fetchAllLevels(): Collection;

    public function fetchLevel(int $levelId): Level;

    /**
     * Validate a player's raw submission for a level, score it, persist the
     * result and return the response body. Each game owns its own payload shape,
     * scoring and response — the controller is a thin dispatcher.
     *
     * Runs inside the caller's transaction, which retries on deadlock, so this
     * method may run more than once for one request. Write to the database and
     * nothing else — an HTTP call or a file write would repeat. A queued job is
     * the one safe exception: after_commit holds it until the commit.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws NotFoundHttpException
     */
    public function submit(User $user, Game $game, int $levelId, array $payload): array;
}
