<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyProfileRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService
    ) {}

    /**
     * Email a one-time deletion code to a password-less account.
     *
     * Password accounts get a 409: they confirm deletion with their password,
     * so a code would only add a second, weaker path. Not a 422 — the request
     * has no input to correct; the conflict is with the account's state.
     *
     * @OA\Post(
     *     path="/api/profile/deletion-code",
     *     summary="Request an account-deletion confirmation code",
     *     description="Emails a one-time code to the account address. Only for password-less (Google-only) accounts; password accounts confirm deletion with their password.",
     *     operationId="sendAccountDeletionCode",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Code sent",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="A confirmation code has been sent to your email.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Account has a password and must confirm with it"),
     *     @OA\Response(response=429, description="Too many code requests")
     * )
     */
    public function sendCode(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasPassword()) {
            return new JsonResponse(
                ['message' => __('exceptions.account_deletion.code_not_applicable')],
                Response::HTTP_CONFLICT,
            );
        }

        $this->accountDeletionService->sendCode($user);

        return new JsonResponse([
            'message' => __('exceptions.account_deletion.code_sent'),
        ]);
    }

    /**
     * Permanently delete the authenticated account.
     *
     * The confirmation step depends on the account type (enforced by the
     * request rules): password accounts re-enter their password, password-less
     * accounts supply the one-time emailed code.
     *
     * @OA\Delete(
     *     path="/api/profile",
     *     summary="Delete the authenticated account",
     *     description="Erases the account and its data. Requires 'password' for password accounts or 'code' (one-time, emailed) for password-less accounts. Consent records are kept in anonymized form.",
     *     operationId="deleteProfile",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="password", type="string", example="secret123", description="For password accounts"),
     *             @OA\Property(property="code", type="string", example="483920", description="For password-less accounts")
     *         )
     *     ),
     *
     *     @OA\Response(response=204, description="Account deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Wrong password or invalid/expired code"),
     *     @OA\Response(response=429, description="Too many attempts")
     * )
     */
    public function destroy(DestroyProfileRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPassword()) {
            $code = $request->string('code')->toString();

            if (! $this->accountDeletionService->consumeCode($user, $code)) {
                throw ValidationException::withMessages([
                    'code' => __('exceptions.account_deletion.invalid_code'),
                ]);
            }
        }

        $this->accountDeletionService->deleteAccount($user);

        return response()->noContent();
    }
}
