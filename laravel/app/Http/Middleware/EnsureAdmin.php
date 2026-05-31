<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block requests from unauthenticated users (401) and from authenticated
 * users without admin privileges (403). Typically paired with auth:sanctum
 * upstream, but also produces correct HTTP codes when used standalone.
 */
class EnsureAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        if (! $user->is_admin) {
            abort(Response::HTTP_FORBIDDEN, 'Admin privileges required.');
        }

        return $next($request);
    }
}
