<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;
use App\Services\Entitlement\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates opening a level. One call to recordOpen(), which records the open for
 * every account and enforces the start allowance only where the tier is bounded
 * — splitting that into "record" then "check" here would reintroduce a way to
 * forget the check.
 *
 * Runs after the controller: only a level that was actually delivered counts as
 * an open, so a 404 for one the admin removed does not spend the day. The cost
 * is that a request about to be refused assembles the level first — every time,
 * not once. The refusal still precedes delivery: the exception discards the
 * response the controller built.
 *
 * No try/catch: the 403 shape belongs to the Handler, and a second copy of it
 * here would drift from the one the submit gate uses.
 */
class EnforceLevelStart
{
    public function __construct(
        private EntitlementService $entitlement,
        private DailyUsageService $usage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');

        if (! $game instanceof Game) {
            throw new LogicException(sprintf(
                'EnforceLevelStart expected a resolved Game for the {game} route parameter, got %s.',
                get_debug_type($game),
            ));
        }

        /** @var User $user */
        $user = $request->user();

        $response = $next($request);

        if ($response->isSuccessful()) {
            $this->usage->recordOpen(
                $user,
                $this->entitlement->resolveTier($user),
                $game->id,
                (int) $request->route('level'),
            );
        }

        return $response;
    }
}
