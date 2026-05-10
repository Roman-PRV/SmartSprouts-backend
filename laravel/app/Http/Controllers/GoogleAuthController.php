<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\GoogleAuthService;
use App\Services\GoogleOAuthStateGuard;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google OAuth consent page.
     *
     * Returns the authorization URL for the frontend to redirect the user to,
     * along with a signed HttpOnly cookie that binds the callback to this
     * client (CSRF protection via the OAuth `state` parameter).
     */
    public function redirect(GoogleOAuthStateGuard $guard): JsonResponse
    {
        ['url' => $url, 'cookie' => $cookie] = $guard->start();

        return (new JsonResponse(['url' => $url]))->withCookie($cookie);
    }

    /**
     * Handle the callback from Google after user authorization.
     *
     * Validates the OAuth state, exchanges the authorization code for the
     * Google user, finds or creates the corresponding local user, and
     * issues a Sanctum token.
     */
    public function callback(
        Request $request,
        GoogleAuthService $authService,
        GoogleOAuthStateGuard $guard,
    ): JsonResponse {
        if (! $guard->validate($request)) {
            return $this->respondAndForgetState($guard, 'Invalid OAuth state.', 401);
        }

        try {
            $googleUser = $guard->retrieveGoogleUser();
        } catch (Exception $e) {
            report($e);

            return $this->respondAndForgetState($guard, 'Google authentication failed.', 401);
        }

        try {
            $user = $authService->findOrCreateUser($googleUser);
        } catch (InvalidArgumentException $e) {
            report($e);

            return $this->respondAndForgetState($guard, 'Google account data is incomplete or invalid.', 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return (new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]))->withCookie($guard->forgetCookie());
    }

    private function respondAndForgetState(GoogleOAuthStateGuard $guard, string $message, int $status): JsonResponse
    {
        return (new JsonResponse(['message' => $message], $status))
            ->withCookie($guard->forgetCookie());
    }
}
