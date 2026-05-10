<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

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
     *
     * @throws InvalidStateException
     */
    public function callback(): JsonResponse
    {
        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();
        } catch (InvalidStateException $e) {
            return new JsonResponse(['message' => 'Invalid OAuth state.'], 401);
        } catch (Throwable $e) {
            return new JsonResponse(['message' => 'Google authentication failed.'], 401);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user !== null) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }
        }

        if ($user === null) {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }
}
