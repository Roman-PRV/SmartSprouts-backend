<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfilePasswordController extends Controller
{
    /**
     * Update the authenticated user's password.
     *
     * Validates the current password, hashes and saves the new one,
     * revokes all other active Sanctum tokens (other sessions),
     * and clears any pending password reset tokens.
     *
     * @OA\Put(
     *     path="/api/profile/password",
     *     summary="Update authenticated user password",
     *     description="Validates the current password, updates it with a new hash, invalidates all other active sessions, and clears pending password reset tokens.",
     *     operationId="updateUserPassword",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "new_password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", example="secret123"),
     *             @OA\Property(property="new_password", type="string", minLength=6, example="newSecret456"),
     *             @OA\Property(property="new_password_confirmation", type="string", example="newSecret456")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Password updated successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     )
     * )
     */
    public function update(UpdatePasswordRequest $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->password = Hash::make($request->new_password);
        $user->save();

        /** @var \Laravel\Sanctum\PersonalAccessToken $currentToken */
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        return response()->noContent();
    }
}
