<?php

namespace App\Http\Controllers;

use App\Exceptions\TableMissingException;
use App\Http\Requests\CheckAnswersRequest;
use App\Http\Resources\LevelDescriptionResource;
use App\Models\Game;
use App\Services\GameResultService;
use App\Services\GameServiceFactory;
use App\Services\ResourceResolver;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @OA\Tag(
 *     name="Levels",
 *     description="API for game levels"
 * )
 */
class LevelController extends Controller
{
    public function __construct(
        protected GameServiceFactory $factory,
        protected ResourceResolver $resources,
        protected GameResultService $gameResults,
    ) {}

    /**
     * List levels for a game
     *
     * @OA\Get(
     *     path="/api/games/{game}/levels",
     *     tags={"Levels"},
     *     summary="Get levels for a game",
     *     description="Returns collection of levels for the specified game. Returns a 404 response if the underlying levels table for the game is missing.",
     *
     *     @OA\Parameter(
     *         name="game",
     *         in="path",
     *         description="Game identifier (route-model bound). The controller resolves the game and its table_prefix.",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of levels",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LevelCollection")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Levels table missing",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Game $game): JsonResponse
    {
        try {
            $service = $this->factory->for($game);
            $levels = $service->fetchAllLevels();
        } catch (TableMissingException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(LevelDescriptionResource::collection($levels)->resolve(request()), 200);
    }

    /**
     * Get single level by id
     *
     * @OA\Get(
     *     path="/api/games/{game}/levels/{levelId}",
     *     tags={"Levels"},
     *     summary="Get a level",
     *     description="Returns single level data for the specified game and level id. Returns a 404 response when the level or levels table is missing.",
     *
     *     @OA\Parameter(
     *         name="game",
     *         in="path",
     *         description="Game identifier (route-model bound).",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="levelId",
     *         in="path",
     *         description="Level id",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=42)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Level data",
     *
     *        @OA\JsonContent(ref="#/components/schemas/Level")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Level not found or levels table missing",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show(Game $game, int $levelId): JsonResponse
    {
        try {
            $service = $this->factory->for($game);
            $level = $service->fetchLevel($levelId);
        } catch (TableMissingException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $resource = $this->resources->resourceFor($game, $level);

        return response()->json($resource->resolve(request()), 200);
    }

    /**
     * Check player answers for a level
     *
     * @OA\Post(
     *     path="/api/games/{game}/levels/{levelId}/check",
     *     tags={"Levels"},
     *     summary="Check player answers for a level",
     *     description="Validates submitted answers and returns whether each answer is correct. Returns validation errors (422) if the payload is invalid or statements don't belong to the specified level, and 404 if the game, level, or relevant table is not found.",
     *
     *     @OA\Parameter(
     *         name="game",
     *         in="path",
     *         description="Game identifier (route-model bound).",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="levelId",
     *         in="path",
     *         description="Level id",
     *         required=true,
     *
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Player answers to validate",
     *
     *         @OA\JsonContent(ref="#/components/schemas/CheckAnswersRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Validation results",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="results",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/TrueFalseImage.Result")
     *             ),
     *             example={
     *                 "results": {
     *                     {
     *                         "statement_id": 10,
     *                         "correct": true,
     *                         "is_true": true,
     *                         "explanation": "Because of Rayleigh scattering"
     *                     },
     *                     {
     *                         "statement_id": 11,
     *                         "correct": false,
     *                         "is_true": false,
     *                         "explanation": "Cats cannot fly naturally"
     *                     }
     *                 }
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Game or level not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\Models\Game] 99999")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - invalid payload format, missing required fields, or statements don't belong to the specified level",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="The answers field is required."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "answers": {"The statement 20 does not belong to level 1."},
     *                     "answers.0.answer": {"The answers.0.answer field is required."}
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - service configuration error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="No game service configured for table prefix: invalid_prefix")
     *         )
     *     )
     * )
     */
    public function check(CheckAnswersRequest $request, Game $game): JsonResponse
    {
        $dto = $request->toDTO();
        try {
            $service = $this->factory->for($dto->game);
            $results = $service->check($dto);
            $this->gameResults->save($dto->game, $dto->levelId, $results);
        } catch (TableMissingException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json($results, 200);
    }
}
