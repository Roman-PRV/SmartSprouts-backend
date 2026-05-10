<?php

namespace App\Services;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthService
{
    public function findOrCreateUser(SocialiteUser $googleUser): User
    {
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
                'email_verified_at' => now(),
            ]);
        }

        return $user;
    }
}
