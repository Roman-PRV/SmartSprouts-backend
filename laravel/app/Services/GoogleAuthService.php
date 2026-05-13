<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthService
{
    public function findOrCreateUser(SocialiteUser $googleUser): User
    {
        $id = $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName();

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Google OAuth response is missing a valid user ID.');
        }

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Google OAuth response is missing a valid email address.');
        }

        // Use the local part of the email as a safe fallback when the display name is absent.
        if (! is_string($name) || trim($name) === '') {
            $name = explode('@', $email)[0];
        }

        $user = User::query()->where('google_id', $id)->first();

        if ($user === null) {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                $user->update([
                    'google_id' => $id,
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }
        }

        if ($user === null) {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'google_id' => $id,
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
        }

        return $user;
    }
}
