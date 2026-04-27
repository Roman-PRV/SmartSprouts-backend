<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;

class ProfilePasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwordService) {}

    /**
     * Update the authenticated user's password.
     *
     * Validates the current password, hashes and saves the new one inside a
     * single DB transaction that also revokes **all** existing Sanctum tokens
     * and clears any pending password reset tokens. A fresh Bearer token is
     * issued atomically and returned to the client so the session stays alive.
     *
     * @OA\Put(
     *     path="/api/profile/password",
     *     summary="Update authenticated user password",
     *     description="Validates the current password, updates it with a new hash, revokes all existing tokens, clears pending password reset tokens, and returns a fresh Bearer token.",
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
     *             @OA\Property(property="new_password", type="string", minLength=8, example="newSecret456"),
     *             @OA\Property(property="new_password_confirmation", type="string", example="newSecret456")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password updated — fresh token issued",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
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
    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $token = $this->passwordService->update($user, $request->new_password);

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
