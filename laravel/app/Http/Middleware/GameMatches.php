<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests whose route-bound Game does not match the expected
 * table_prefix. Used to scope per-game admin endpoints under the universal
 * /api/admin/games/{game}/... URL so the frontend never needs game-specific
 * slugs while controllers stay strictly per-game.
 *
 * Two distinct failure modes:
 *  - Game model could not be resolved (developer forgot a Game $game
 *    type-hint in the action method, so SubstituteBindings left {game}
 *    as a raw string) → LogicException, surfaces as 500 with full trace.
 *  - Game exists but its table_prefix differs → 404, regular client error.
 */
class GameMatches
{
    public function handle(Request $request, Closure $next, string $expectedPrefix): Response
    {
        $game = $request->route('game');

        if (! $game instanceof Game) {
            throw new LogicException(sprintf(
                'GameMatches middleware expected a resolved Game instance for the {game} route parameter, got %s. '.
                'Add a Game $game type-hint to one of the action method parameters so Laravel implicit route '.
                'model binding can resolve the {game} segment before this middleware runs.',
                get_debug_type($game)
            ));
        }

        if ($game->table_prefix !== $expectedPrefix) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
