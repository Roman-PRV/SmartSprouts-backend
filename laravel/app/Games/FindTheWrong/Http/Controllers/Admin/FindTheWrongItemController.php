<?php

namespace App\Games\FindTheWrong\Http\Controllers\Admin;

use App\Games\FindTheWrong\Http\Requests\Admin\StoreItemRequest;
use App\Games\FindTheWrong\Http\Requests\Admin\UpdateItemRequest;
use App\Games\FindTheWrong\Http\Resources\Admin\FindTheWrongItemAdminResource;
use App\Games\FindTheWrong\Services\Admin\FindTheWrongItemAdminService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Admin endpoint for find-the-wrong items. Items are game-specific (polygon
 * shape only makes sense here), so this controller is per-game rather than
 * dispatched — direct dependency on the concrete service and resource.
 *
 * The Game $game parameter is unused inside method bodies (GameMatches
 * middleware already validated the prefix), but its type-hint is what triggers
 * Laravel's implicit route model binding for {game}. Without it,
 * SubstituteBindings leaves {game} as a raw string and GameMatches sees no
 * model — every request would 404.
 */
class FindTheWrongItemController extends Controller
{
    public function __construct(protected FindTheWrongItemAdminService $service) {}

    public function store(StoreItemRequest $request, Game $game, int $level): JsonResponse
    {
        $item = $this->service->create($level, $request->validated());

        return response()->json(
            FindTheWrongItemAdminResource::make($item)->resolve(request()),
            201
        );
    }

    public function update(UpdateItemRequest $request, Game $game, int $item): JsonResponse
    {
        $updated = $this->service->update($item, $request->validated());

        return response()->json(
            FindTheWrongItemAdminResource::make($updated)->resolve(request())
        );
    }

    public function destroy(Game $game, int $item): Response
    {
        $this->service->delete($item);

        return response()->noContent();
    }
}
