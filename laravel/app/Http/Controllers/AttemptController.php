<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\GatedAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One submit endpoint for every game. GatedAttemptService records the
 * completion and hands off to the concrete game service, which owns validation,
 * scoring, persistence and the response shape. Mirrors the generic read path
 * (one route, dispatched by game).
 *
 * Validation deliberately lives in the service (via Validator), not a
 * FormRequest: a single generic endpoint cannot statically bind a per-game
 * FormRequest because the payload shape depends on the game resolved at runtime.
 *
 * @OA\Tag(name="Attempts", description="Submit a completed level attempt")
 */
class AttemptController extends Controller
{
    public function __construct(protected GatedAttemptService $attempts) {}

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

        return response()->json(
            $this->attempts->submit($user, $game, $level, $request->all()),
            200,
        );
    }
}
