<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Display a listing of active games.
     *
     * @OA\Get(
     *     path="/api/games",
     *     summary="Get list of active games",
     *     description="Returns a list of games that are marked as active.",
     *     operationId="getActiveGames",
     *     tags={"Games"},
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
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $games = Game::where('is_active', true)->get([
            'id',
            'key',
            'icon_url',
            'is_active',
        ]);

        return response()->json(GameResource::collection($games));
    }

    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

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
    public function show(Game $game): JsonResponse
    {
        return response()->json(new GameResource($game));
    }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(string $id)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }
}
