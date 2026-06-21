<?php

namespace App\Games\Arithmetic\Http\Controllers;

use App\Games\Arithmetic\Http\Requests\ArithmeticAttemptRequest;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameResultService;
use App\Services\GameServiceFactory;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic attempt endpoint for every arithmetic game. The concrete game service
 * is resolved from the request's Game (table_prefix → subclass) via the
 * factory, so a single controller serves multiplication, addition and any
 * future operation.
 *
 * @OA\Tag(
 *     name="Arithmetic",
 *     description="Arithmetic pair-match games — player endpoints"
 * )
 */
class ArithmeticAttemptController extends Controller
{
    public function __construct(
        protected GameServiceFactory $factory,
        protected GameResultService $gameResults,
    ) {}

    /**
     * Score a completed arithmetic level and persist the result.
     *
     * The Game $game parameter is type-hinted only to trigger Laravel's implicit
     * route model binding so the GameMatches middleware (registered on the
     * per-game route) sees a resolved Game; the service is resolved from the DTO.
     *
     * @OA\Post(
     *     path="/api/games/{game}/levels/{level}/attempts",
     *     tags={"Arithmetic"},
     *     summary="Submit answers for an arithmetic level",
     *     description="Validates the answers, scores them server-side and stores the result.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="game", in="path", required=true, @OA\Schema(type="integer", example=4)),
     *     @OA\Parameter(name="level", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Arithmetic.AttemptRequest")),
     *
     *     @OA\Response(response=200, description="Scored results"),
     *     @OA\Response(response=400, description="Service misconfiguration for the game prefix", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=404, description="Game or level not found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function store(ArithmeticAttemptRequest $request, Game $game): JsonResponse
    {
        $dto = $request->toDTO();

        try {
            $service = $this->factory->for($dto->game);
            $results = $service->check($dto);
            $this->gameResults->save($dto, $results);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json($results, 200);
    }
}
