<?php

namespace App\Services\Entitlement;

use App\Exceptions\Entitlement\DailyCompletedLimitExceededException;
use App\Exceptions\Entitlement\LevelNotOpenedTodayException;
use App\Models\Game;
use App\Models\User;
use App\Services\GameServiceFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Records the completion and scores the attempt in one transaction, so a
 * refused completion takes the game_results row the game just wrote with it.
 */
class GatedAttemptService
{
    public function __construct(
        private GameServiceFactory $factory,
        private EntitlementService $entitlement,
        private DailyUsageService $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws LevelNotOpenedTodayException
     * @throws DailyCompletedLimitExceededException
     * @throws ValidationException
     * @throws NotFoundHttpException
     */
    public function submit(User $user, Game $game, int $level, array $payload): array
    {
        $tier = $this->entitlement->resolveTier($user);

        // Marked before scoring, so a refusal costs no scoring work.
        return DB::transaction(function () use ($user, $tier, $game, $level, $payload): array {
            $this->usage->recordCompletion($user, $tier, $game->id, $level);

            return $this->factory->for($game)->submit($user, $game, $level, $payload);
        }, DailyUsageService::DEADLOCK_ATTEMPTS);
    }
}
