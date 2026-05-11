<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Issues and verifies the cryptographic `state` parameter that protects the
 * Google OAuth flow from CSRF. The state is stored in an encrypted, HttpOnly
 * cookie so the API can stay session-less.
 */
class GoogleOAuthStateGuard
{
    public const COOKIE_NAME = 'google_oauth_state';

    private const COOKIE_TTL_MINUTES = 10;

    private const STATE_LENGTH = 32;

    /**
     * Begin the OAuth flow: generate state, build the Google authorization
     * URL, and prepare the cookie that binds the callback to this client.
     *
     * @return array{url: string, cookie: Cookie}
     */
    public function start(): array
    {
        $state = Str::random(self::STATE_LENGTH);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return [
            'url' => $url,
            'cookie' => $this->makeStateCookie(Crypt::encryptString($state)),
        ];
    }

    /**
     * Verify that the state query parameter matches the encrypted cookie.
     */
    public function validate(Request $request): bool
    {
        $stateFromQuery = $request->query('state');
        $encryptedCookie = $request->cookie(self::COOKIE_NAME);

        if (! is_string($stateFromQuery) || $stateFromQuery === '') {
            return false;
        }

        if (! is_string($encryptedCookie) || $encryptedCookie === '') {
            return false;
        }

        try {
            $stateFromCookie = Crypt::decryptString($encryptedCookie);
        } catch (DecryptException $e) {
            report($e);

            return false;
        }

        return hash_equals($stateFromCookie, $stateFromQuery);
    }

    /**
     * Exchange the authorization code on the callback request for the
     * authenticated Google user. State must already have been validated.
     */
    public function retrieveGoogleUser(): SocialiteUser
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->stateless()->user();
    }

    /**
     * Cookie that, when attached to a response, clears the one-shot state.
     */
    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME, '/');
    }

    private function makeStateCookie(string $value): Cookie
    {
        return cookie(
            name: self::COOKIE_NAME,
            value: $value,
            minutes: self::COOKIE_TTL_MINUTES,
            path: '/',
            domain: null,
            secure: ! app()->environment('local'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
