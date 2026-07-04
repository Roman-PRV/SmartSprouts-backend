<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegenerateAudioRequest;
use App\Http\Requests\Admin\StoreStatementRequest;
use App\Http\Requests\Admin\UpdateStatementRequest;
use App\Http\Resources\Admin\StatementAdminResource;
use App\Models\Game;
use App\Services\StatementAdminService;
use App\Services\StatementAdminServiceFactory;
use App\Services\Tts\TtsRegenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Generic admin endpoint for true/false statements. The statement form is
 * identical across games, so writes dispatch to the shared
 * StatementAdminService resolved per game by the factory — mirroring the
 * generic Admin\LevelController for levels.
 */
class StatementController extends Controller
{
    public function __construct(
        protected StatementAdminServiceFactory $factory,
        protected TtsRegenerationService $regeneration,
    ) {}

    public function store(StoreStatementRequest $request, Game $game, int $level): JsonResponse
    {
        $statement = $this->resolveService($game)->create($level, $request->validated());

        return StatementAdminResource::make($statement)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateStatementRequest $request, Game $game, int $statement): JsonResource
    {
        $updated = $this->resolveService($game)->update($statement, $request->validated());

        return StatementAdminResource::make($updated);
    }

    public function destroy(Game $game, int $statement): Response
    {
        $this->resolveService($game)->delete($statement);

        return response()->noContent();
    }

    public function regenerateAudio(RegenerateAudioRequest $request, Game $game, int $statement): Response
    {
        $model = $this->resolveService($game)->find($statement);

        $this->regeneration->regenerate(
            $model,
            $request->validated('field'),
            $request->validated('locale'),
        );

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * Resolve the statement admin service for this game. Aborts 404 with a
     * fixed message when no statement model is configured; the factory error is
     * logged instead of leaking class names.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function resolveService(Game $game): StatementAdminService
    {
        try {
            return $this->factory->for($game);
        } catch (InvalidArgumentException $e) {
            Log::warning('Statement admin service not configured for game', [
                'game_id' => $game->id,
                'table_prefix' => $game->table_prefix,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Admin operations are not available for this game.');
        }
    }
}
