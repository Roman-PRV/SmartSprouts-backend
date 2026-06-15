<?php

namespace App\Games\FindTheWrong\Http\Controllers\Admin;

use App\Games\FindTheWrong\Http\Resources\Admin\FindTheWrongLevelAdminResource;
use App\Games\FindTheWrong\Services\Admin\FindTheWrongLevelAdminService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin endpoint for reading a single FindTheWrong level with its items.
 *
 * Write operations (store, update, destroy) are handled by the generic
 * AdminLevelController dispatched via LevelAdminServiceFactory — only the
 * read-with-items endpoint is game-specific, so it lives here.
 *
 * The Game $game parameter is unused inside the method body (GameMatches
 * middleware already validated the prefix), but its type-hint is what triggers
 * Laravel's implicit route model binding for {game}. Without it,
 * SubstituteBindings leaves {game} as a raw string and GameMatches sees no
 * model — every request would 404.
 */
class FindTheWrongLevelController extends Controller
{
    public function __construct(protected FindTheWrongLevelAdminService $service) {}

    public function show(Game $game, int $level): JsonResource
    {
        return FindTheWrongLevelAdminResource::make(
            $this->service->find($level)
        );
    }
}
