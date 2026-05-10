<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\GoogleAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google OAuth consent page.
     *
     * Returns the authorization URL for the frontend to redirect the user to.
     */
    public function redirect(): JsonResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver->stateless()->redirect()->getTargetUrl();

        return new JsonResponse(['url' => $url]);
    }

    /**
     * Handle the callback from Google after user authorization.
     *
     * Finds or creates a user based on Google account data,
     * then issues a Sanctum token.
     */
    public function callback(GoogleAuthService $service): JsonResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();
        } catch (InvalidStateException $e) {
            report($e);

            return new JsonResponse(['message' => 'Invalid OAuth state.'], 401);
        } catch (Exception $e) {
            report($e);

            return new JsonResponse(['message' => 'Google authentication failed.'], 401);
        }

        $user = $service->findOrCreateUser($googleUser);
        $token = $user->createToken('auth_token')->plainTextToken;

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }
}
