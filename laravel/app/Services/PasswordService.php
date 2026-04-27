<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class PasswordService
{
    public function update(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword) {
            $user->update(['password' => Hash::make($newPassword)]);

            $currentToken = $user->currentAccessToken();
            if ($currentToken instanceof PersonalAccessToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            }

            DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->delete();
        });
    }
}
