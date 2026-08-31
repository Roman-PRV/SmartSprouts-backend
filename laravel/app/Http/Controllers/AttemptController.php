<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use App\Services\Entitlement\EntitlementService;
use App\Services\GameServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One submit endpoint for every game. The concrete game service is resolved
 * from the route's Game (table_prefix → service) via the factory, and it owns
 * validation, scoring, persistence and the response shape. Mirrors the generic
 * read path (one route, dispatched by game).
 *
 * Validation deliberately lives in the service (via Validator), not a
 * FormRequest: a single generic endpoint cannot statically bind a per-game
 * FormRequest because the payload shape depends on the game resolved at runtime.
 *
 * @OA\Tag(name="Attempts", description="Submit a completed level attempt")
 */
class AttemptController extends Controller
{
    public function __construct(
        protected GameServiceFactory $factory,
        protected EntitlementService $entitlement,
        protected DailyUsageService $usage,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/games/{game}/levels/{level}/attempts",
     *     tags={"Attempts"},
     *     summary="Submit a completed attempt for a level",
     *     description="Validates and scores the player's submission for the given game and level, stores the result, and returns the game-specific response. The request body and response shape depend on the game.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="game", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="level", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(oneOf={
     *
     *         @OA\Schema(ref="#/components/schemas/TrueFalse.AttemptRequest"),
     *         @OA\Schema(ref="#/components/schemas/Arithmetic.AttemptRequest"),
     *         @OA\Schema(ref="#/components/schemas/FindTheWrong.AttemptRequest")
     *     })),
     *
     *     @OA\Response(response=200, description="Scored result (shape depends on the game)", @OA\JsonContent(oneOf={
     *
     *         @OA\Schema(ref="#/components/schemas/TrueFalse.AttemptResponse"),
     *         @OA\Schema(ref="#/components/schemas/Arithmetic.AttemptResponse"),
     *         @OA\Schema(ref="#/components/schemas/FindTheWrong.AttemptResponse")
     *     })),
     *
     *     @OA\Response(response=400, description="Service misconfiguration for the game prefix", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=403, description="Daily completion allowance spent, or the level was not opened today", @OA\JsonContent(oneOf={
     *
     *         @OA\Schema(ref="#/components/schemas/DailyLimitReachedResponse"),
     *         @OA\Schema(ref="#/components/schemas/LevelNotOpenedTodayResponse")
     *     })),
     *
     *     @OA\Response(response=404, description="Game or level not found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function store(Request $request, Game $game, int $level): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tier = $this->entitlement->resolveTier($user);

        // One transaction over both: a refused completion has to take the
        // game_results row the service writes down with it. Marked before
        // scoring, so a refusal costs no scoring work.
        $results = DB::transaction(function () use ($user, $tier, $game, $level, $request): array {
            $this->usage->recordCompletion($user, $tier, $game->id, $level);

            return $this->factory->for($game)->submit($user, $game, $level, $request->all());
        }, DailyUsageService::DEADLOCK_ATTEMPTS);

        return response()->json($results, 200);
    }
}
