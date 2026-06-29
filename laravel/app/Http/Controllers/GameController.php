<?php

namespace App\Http\Controllers;

use App\Enums\GameCategory;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    /**
     * Display a listing of active games.
     *
     * @OA\Get(
     *     path="/api/games",
     *     summary="Get list of active games",
     *     description="Returns a list of games that are marked as active, optionally filtered by category.",
     *     operationId="getActiveGames",
     *     tags={"Games"},
     *
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         description="Return only games belonging to this learning area.",
     *
     *         @OA\Schema(type="string", enum={"math", "reading", "logic"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with list of games",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/Game")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid category value"
     *     )
     * )
     */
    public function index(Request $request): JsonResource
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::enum(GameCategory::class)],
        ]);

        $games = Game::where('is_active', true)->get([
            'id',
            'key',
            'icon_url',
            'is_active',
            'categories',
        ]);

        // Filter in PHP rather than via whereJsonContains: the games table is tiny
        // and SQLite (used in tests) has no JSON-contains operator. A category is
        // present when the game's array includes it.
        if (isset($validated['category'])) {
            $games = $games
                ->filter(fn (Game $game) => in_array($validated['category'], $game->categories ?? [], true))
                ->values();
        }

        return GameResource::collection($games);
    }

    /**
     * Display the specified game details.
     *
     * @OA\Get(
     * path="/api/games/{id}",
     * summary="Get details for a specific game",
     * description="Returns the full details for a game by its ID.",
     * operationId="getGameDetails",
     * tags={"Games"},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the game to retrieve",
     *
     * @OA\Schema(
     * type="integer"
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Successful response with game details",
     *
     * @OA\JsonContent(ref="#/components/schemas/Game")
     * ),
     *
     * @OA\Response(
     * response=404,
     * description="Game not found"
     * )
     * )
     */
    public function show(Game $game): JsonResource
    {
        return new GameResource($game);
    }
}
