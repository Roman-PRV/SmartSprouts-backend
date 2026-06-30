<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Contracts\TtsAudioInterface;
use App\Helpers\ConfigHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegenerateAudioRequest;
use App\Http\Requests\Admin\StoreLevelRequest;
use App\Http\Requests\Admin\UpdateLevelRequest;
use App\Http\Resources\Admin\LevelAdminResource;
use App\Models\Game;
use App\Services\LevelAdminServiceFactory;
use App\Services\Tts\TtsRegenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Single generic admin endpoint for game levels (index/show/store/update/
 * destroy/regenerate). Per-game write logic is dispatched to a
 * LevelAdminServiceInterface implementation resolved via the factory; the
 * single-level read shape is dispatched to a per-game resource via config.
 *
 * Games whose level form diverges (TrueFalseText carries a translatable `text`
 * body and an optional image) still flow through here: the game-aware
 * Store/UpdateLevelRequest applies the right rules, and each service reads only
 * the fields it needs. This keeps every game on the same slug-free
 * admin/games/{game}/levels URLs.
 */
class LevelController extends Controller
{
    public function __construct(
        protected LevelAdminServiceFactory $factory,
        protected TtsRegenerationService $regeneration,
    ) {}

    /**
     * List all levels for the given game (admin view, includes the relation count).
     */
    public function index(Game $game): JsonResource
    {
        return LevelAdminResource::collection($this->resolveService($game)->list());
    }

    /**
     * Read a single level with its game-specific relations, via the per-game
     * admin resource resolved from config.
     */
    public function show(Game $game, int $level): JsonResource
    {
        $model = $this->resolveService($game)->find($level);
        $resourceClass = $this->resolveResourceClass($game);

        return $resourceClass::make($model);
    }

    /**
     * Create a new level. Returns 201 with the persisted level payload.
     */
    public function store(StoreLevelRequest $request, Game $game): JsonResponse
    {
        /** @var UploadedFile|null $image */
        $image = $request->file('image');

        $level = $this->resolveService($game)->create($this->levelData($request), $image);
        $resourceClass = $this->resolveResourceClass($game);

        return $resourceClass::make($level)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a level's metadata; image is replaced only when a file is attached.
     */
    public function update(UpdateLevelRequest $request, Game $game, int $level): JsonResource
    {
        /** @var UploadedFile|null $image */
        $image = $request->file('image');

        $updated = $this->resolveService($game)->update($level, $this->levelData($request), $image);
        $resourceClass = $this->resolveResourceClass($game);

        return $resourceClass::make($updated);
    }

    /**
     * Delete a level. Related rows cascade via FK; storage cleanup is best-effort.
     */
    public function destroy(Game $game, int $level): Response
    {
        $this->resolveService($game)->delete($level);

        return response()->noContent();
    }

    /**
     * Queue a per-(field, locale) TTS regeneration for one of the level's audio
     * fields. Complements the observer-driven auto-TTS (retry for queue/quota
     * failures, imported or hand-edited data).
     */
    public function regenerateAudio(RegenerateAudioRequest $request, Game $game, int $level): Response
    {
        $model = $this->resolveService($game)->find($level);

        if (! $model instanceof TtsAudioInterface) {
            abort(404, 'Audio regeneration is not available for this game.');
        }

        $this->regeneration->regenerate(
            $model,
            $request->validated('field'),
            $request->validated('locale'),
        );

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * The validated translatable level fields to forward to the service. Title
     * is always present; `text` only for games whose request validated it.
     *
     * @return array<string, mixed>
     */
    private function levelData(StoreLevelRequest|UpdateLevelRequest $request): array
    {
        return $request->safe()->only(['title', 'text']);
    }

    /**
     * Look up the admin service for this game. Aborts 404 with a fixed message
     * if no implementation is registered; the factory error is logged instead
     * of leaking class names into the response.
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

    /**
     * Per-game single-level resource, falling back to the generic shape.
     *
     * @return class-string<JsonResource>
     */
    private function resolveResourceClass(Game $game): string
    {
        $map = ConfigHelper::getStringMap('games.admin_level_resources', []);
        $class = $map[$game->table_prefix ?? ''] ?? null;

        if ($class !== null && is_subclass_of($class, JsonResource::class)) {
            return $class;
        }

        return LevelAdminResource::class;
    }
}
