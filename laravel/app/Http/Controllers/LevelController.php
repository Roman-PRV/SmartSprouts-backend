<?php

namespace App\Http\Controllers;

use App\Http\Resources\LevelDescriptionResource;
use App\Models\Game;
use App\Models\Level;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LevelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/games/{game}/levels",
     *     summary="Get levels for a game",
     *     description="Return list of levels for the specified game. Table prefix is read from the game record and used to query the corresponding levels table. Levels are ordered by created_at ascending.",
     *     operationId="getGameLevels",
     *     tags={"Games","Levels"},
     *     @OA\Parameter(
     *         name="game",
     *         in="path",
     *         required=true,
     *         description="Game id (route model binding)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of level descriptions",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Level")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid table prefix or failed to read levels table",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid table prefix")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Game not found or levels table not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Levels table not found")
     *         )
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Game $game): JsonResponse|LevelDescriptionResource
    {
        $prefix = $game->table_prefix;
        // validate prefix to avoid injection
        if (! is_string($prefix) || $prefix === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            return response()->json(['message' => 'Invalid table prefix'], Response::HTTP_BAD_REQUEST);
        }

        $table = $prefix . '_levels';

        if (! Schema::hasTable($table)) {
            return response()->json(['message' => 'Levels table not found'], Response::HTTP_NOT_FOUND);
        }

        $levelModel = (new Level)->setTableForPrefix($prefix);

        try {
            $levels = $levelModel->select(['id', 'title', 'image_url', 'created_at'])
                ->orderBy('created_at', 'asc')
                ->get();
        } catch (QueryException $e) {
            return response()->json(['message' => 'Failed to read levels table'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(LevelDescriptionResource::collection($levels), Response::HTTP_OK);
    }
    //
}
