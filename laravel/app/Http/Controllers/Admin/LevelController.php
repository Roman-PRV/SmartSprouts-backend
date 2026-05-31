<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLevelRequest;
use App\Http\Requests\Admin\UpdateLevelRequest;
use App\Http\Resources\Admin\LevelAdminResource;
use App\Models\Game;
use App\Services\LevelAdminServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Generic admin endpoint for game levels. Dispatches per-game write operations
 * to a LevelAdminServiceInterface implementation resolved via the factory.
 */
class LevelController extends Controller
{
    public function __construct(protected LevelAdminServiceFactory $factory) {}

    /**
     * List all levels for the given game (admin view, includes items_count).
     */
    public function index(Game $game): JsonResponse
    {
        $service = $this->resolveService($game);

        return response()->json(
            LevelAdminResource::collection($service->list())->resolve(request())
        );
    }

    /**
     * Create a new level. Returns 201 with the persisted level payload.
     */
    public function store(StoreLevelRequest $request, Game $game): JsonResponse
    {
        $service = $this->resolveService($game);

        /** @var UploadedFile $image */
        $image = $request->file('image');

        $level = $service->create([
            'title' => $request->validated('title'),
        ], $image);

        return response()->json(
            LevelAdminResource::make($level)->resolve(request()),
            201
        );
    }

    /**
     * Update a level's metadata; image is replaced only when a file is attached.
     */
    public function update(UpdateLevelRequest $request, Game $game, int $level): JsonResponse
    {
        $service = $this->resolveService($game);

        /** @var UploadedFile|null $image */
        $image = $request->file('image');

        $updated = $service->update($level, [
            'title' => $request->validated('title'),
        ], $image);

        return response()->json(
            LevelAdminResource::make($updated)->resolve(request())
        );
    }

    /**
     * Delete a level. Items cascade via FK; storage cleanup is best-effort.
     */
    public function destroy(Game $game, int $level): JsonResponse
    {
        $service = $this->resolveService($game);
        $service->delete($level);

        return response()->json(null, 204);
    }

    /**
     * Look up the admin service for this game. Aborts 404 with a fixed message
     * if no implementation is registered; the internal factory error is logged
     * for debugging instead of leaking class names into the response.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function resolveService(Game $game): LevelAdminServiceInterface
    {
        try {
            return $this->factory->for($game);
        } catch (InvalidArgumentException $e) {
            Log::warning('Admin level service not configured for game', [
                'game_id' => $game->id,
                'table_prefix' => $game->table_prefix,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Admin operations are not available for this game.');
        }
    }
}
