<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block requests from non-admin users. Assumes the route is already gated by
 * an auth middleware — guests will be rejected before reaching this layer.
 */
class EnsureAdmin
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_admin) {
            abort(Response::HTTP_FORBIDDEN, 'Admin privileges required.');
        }

        return $next($request);
    }
}
