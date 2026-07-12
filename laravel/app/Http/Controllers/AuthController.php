<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    /**
     * Register a new user and record their legal-document consent.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        /** @var array{
         *     name: string,
         *     email: string,
         *     password: string,
         * } $data */
        $data = $request->validated();

        // One transaction: a failed consent write must roll the user back,
        // otherwise a retry dies on the unique-email rule.
        /** @var User $user */
        $user = \DB::transaction(function () use ($data, $request): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $this->consentService->recordAcceptance($user, $request->ip(), $request->userAgent());

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Log in an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return new JsonResponse(['message' => __('exceptions.auth.invalid_credentials')], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 200);
    }

    /**
     * Get the authenticated user.
     *
     * consent_current tells the client whether the user has accepted the
     * current legal-document versions; false triggers the blocking consent
     * screen (Google signups, legacy accounts, version bumps).
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse([
            'user' => new UserResource($user),
            'consent_current' => $this->consentService->hasCurrentConsent($user),
        ]);
    }

    /**
     * Log out the authenticated user on this device only.
     *
     * Revokes just the current access token, so signing out on one device
     * leaves the user's other sessions intact. Revoking every token is reserved
     * for a password change (see PasswordService).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->currentAccessToken();

        // Only a real bearer token can be revoked; a session-based TransientToken
        // (Sanctum SPA mode) has no delete().
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return new JsonResponse(['message' => __('exceptions.auth.logged_out')], 200);
    }
}
