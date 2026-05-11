<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthService;
use App\Services\GoogleOAuthStateGuard;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google OAuth consent page.
     *
     * Returns the authorization URL for the frontend to redirect the user to,
     * along with an encrypted HttpOnly cookie that binds the callback to this
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
     * Google user, finds or creates the corresponding local user, issues a
     * Sanctum token, and redirects the browser to the SPA with the token
     * (or error code) delivered in the URL fragment.
     *
     * The fragment is never sent to the server, never appears in referrer
     * headers, and never lands in access logs — making it the standard
     * carrier for tokens in browser-driven OAuth flows.
     */
    public function callback(
        Request $request,
        GoogleAuthService $authService,
        GoogleOAuthStateGuard $guard,
    ): RedirectResponse {
        if (! $guard->validate($request)) {
            return $this->redirectWithError($guard, 'invalid_state');
        }

        try {
            $googleUser = $guard->retrieveGoogleUser();
        } catch (Exception $e) {
            report($e);

            return $this->redirectWithError($guard, 'auth_failed');
        }

        try {
            $user = $authService->findOrCreateUser($googleUser);
        } catch (InvalidArgumentException $e) {
            report($e);

            return $this->redirectWithError($guard, 'invalid_account');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->redirectToFrontend([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ])->withCookie($guard->forgetCookie());
    }

    /**
     * Redirect to the SPA callback URL with an error code in the fragment
     * and clear the OAuth state cookie.
     */
    private function redirectWithError(GoogleOAuthStateGuard $guard, string $error): RedirectResponse
    {
        return $this->redirectToFrontend(['error' => $error])
            ->withCookie($guard->forgetCookie());
    }

    /**
     * Build a redirect to the SPA callback URL with the given parameters
     * encoded as a URL fragment (`#key=value&...`).
     *
     * @param  array<string, string>  $fragmentParams
     */
    private function redirectToFrontend(array $fragmentParams): RedirectResponse
    {
        $base = rtrim((string) config('services.frontend.url'), '/');
        $target = $base.'/auth/google/callback#'.http_build_query($fragmentParams);

        return redirect()->away($target)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
