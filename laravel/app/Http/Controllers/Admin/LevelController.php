<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLevelRequest;
use App\Http\Requests\Admin\UpdateLevelRequest;
use App\Models\Game;
use App\Models\Level;
use App\Services\LevelAdminServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
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
        $levels = $service->list();

        return response()->json($levels->map(fn (Level $level): array => $this->serialize($level))->all());
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

        return response()->json($this->serialize($level), 201);
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

        return response()->json($this->serialize($updated));
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
     * Look up the admin service for this game; abort 400 if no implementation is registered.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function resolveService(Game $game): LevelAdminServiceInterface
    {
        try {
            return $this->factory->for($game);
        } catch (InvalidArgumentException $e) {
            abort(400, $e->getMessage());
        }
    }

    /**
     * Build the JSON shape for a single level. `items_count` is included only
     * when the relation was counted via withCount() in the service.
     *
     * @return array<string, mixed>
     */
    private function serialize(Level $level): array
    {
        $payload = [
            'id' => $level->id,
            'title' => method_exists($level, 'getTranslations') ? $level->getTranslations('title') : $level->title,
            'image_url' => $level->image_url,
        ];

        if (isset($level->items_count)) {
            $payload['items_count'] = $level->items_count;
        }

        return $payload;
    }
}
