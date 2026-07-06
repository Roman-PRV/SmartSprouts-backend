<?php

namespace App\Http\Controllers;

use App\Http\Resources\LevelDescriptionResource;
use App\Models\Game;
use App\Models\User;
use App\Services\GameServiceFactory;
use App\Services\LevelProgressService;
use App\Services\ResourceResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        protected LevelProgressService $progress,
    ) {}

    /**
     * List levels for a game.
     *
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
     *         @OA\JsonContent(ref="#/components/schemas/LevelDescriptionCollection")
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
    public function index(Request $request, Game $game): JsonResource
    {
        $service = $this->factory->for($game);
        $levels = $service->fetchAllLevels();

        /** @var User $user */
        $user = $request->user();
        $this->progress->decorate($user, $game, $levels);

        return LevelDescriptionResource::collection($levels);
    }

    /**
     * Get single level by id.
     *
     *
     * @OA\Get(
     *     path="/api/games/{game}/levels/{level}",
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
     *         name="level",
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
    public function show(Game $game, int $levelId): JsonResource
    {
        $service = $this->factory->for($game);
        $level = $service->fetchLevel($levelId);

        return $this->resources->resourceFor($game, $level);
    }
}
