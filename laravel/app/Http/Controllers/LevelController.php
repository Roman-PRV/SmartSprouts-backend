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
    public function index(Game $game): JsonResponse|LevelDescriptionResource
    {
        $prefix = $game->table_prefix;
        // validate prefix to avoid injection
        if (! is_string($prefix) || $prefix === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            return response()->json(['message' => 'Invalid table prefix'], Response::HTTP_BAD_REQUEST);
        }

        $table = $prefix.'_levels';

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
