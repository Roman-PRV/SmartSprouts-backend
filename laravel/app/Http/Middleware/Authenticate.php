<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API-only backend: never resolve route('login') (it does not exist).
        // Returning null keeps the AuthenticationException constructible so the
        // Handler's unauthenticated() override can render it as a JSON 401.
        return null;
    }
}
