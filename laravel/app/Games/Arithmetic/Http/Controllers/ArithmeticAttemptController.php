<?php

namespace App\Games\Arithmetic\Http\Controllers;

use App\Games\Arithmetic\Http\Requests\ArithmeticAttemptRequest;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameResultService;
use App\Services\GameServiceFactory;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic attempt endpoint for every arithmetic game. The concrete game service
 * is resolved from the request's Game (table_prefix → subclass) via the
 * factory, so a single controller serves multiplication, addition and any
 * future operation.
 *
 * No OpenAPI annotations here: the route is registered later, when concrete
 * games are wired up, and its `POST .../attempts` path is shared with
 * find-the-wrong (one OpenAPI operation per path+method), so endpoint docs are
 * decided there.
 */
class ArithmeticAttemptController extends Controller
{
    public function __construct(
        protected GameServiceFactory $factory,
        protected GameResultService $gameResults,
    ) {}

    /**
     * Score a completed arithmetic level and persist the result.
     *
     * The Game $game parameter is type-hinted only to trigger Laravel's implicit
     * route model binding so the GameMatches middleware (registered on the
     * per-game route) sees a resolved Game; the service is resolved from the DTO.
     *
     * No OpenAPI operation is declared here: the arithmetic attempts route shares
     * the `POST /api/games/{game}/levels/{level}/attempts` path with
     * find-the-wrong, and the spec allows only one operation per path+method.
     * Endpoint documentation (and how to represent the shared path) is deferred
     * to when concrete games register their routes.
     */
    public function store(ArithmeticAttemptRequest $request, Game $game): JsonResponse
    {
        $dto = $request->toDTO();

        try {
            $service = $this->factory->for($dto->game);
            $results = $service->check($dto);
            $this->gameResults->save($dto, $results);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json($results, 200);
    }
}
