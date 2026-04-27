<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordService
{
    /**
     * Update the user's password, revoke all existing tokens, and issue a new one.
     *
     * The entire operation runs in a single DB transaction: if any step fails,
     * the password change is rolled back, guaranteeing a consistent state.
     *
     * @return string The new plain-text Bearer token.
     */
    public function update(User $user, string $newPassword): string
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->update(['password' => Hash::make($newPassword)]);

            DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->delete();

            $user->tokens()->delete();

            return $user->createToken('auth_token')->plainTextToken;
        });
    }
}
