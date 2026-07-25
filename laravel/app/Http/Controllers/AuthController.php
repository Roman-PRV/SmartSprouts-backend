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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    /**
     * Register a new user and record their legal-document consent.
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     description="Creates an account, records acceptance of the current legal documents, and returns a Bearer token.",
     *     operationId="register",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation", "accepted_terms"},
     *
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", minLength=8, example="Password123!"),
     *             @OA\Property(property="password_confirmation", type="string", example="Password123!"),
     *             @OA\Property(property="accepted_terms", type="boolean", example=true, description="The 18+/guardian affirmation and acceptance of the legal documents; must be true.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Registered — Bearer token issued",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="consent_current", type="boolean", example=true, description="Always true on registration (consent was just recorded); false elsewhere triggers the consent gate.")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation failed (e.g. affirmation not accepted or email taken)")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        /** @var array{
         *     name: string,
         *     email: string,
         *     password: string,
         *     accepted_terms: bool,
         * } $data */
        $data = $request->validated();

        // One transaction: a failed consent write must roll the user back,
        // otherwise a retry dies on the unique-email rule.
        /** @var User $user */
        $user = DB::transaction(function () use ($data, $request): User {
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
            // Literal true: registration just recorded the acceptance.
            'consent_current' => true,
        ], 201);
    }

    /**
     * Log in an existing user.
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Log in an existing user",
     *     description="Verifies credentials and returns a Bearer token plus the account's current-consent status.",
     *     operationId="login",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="Password123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logged in — Bearer token issued",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="consent_current", type="boolean", example=true, description="Whether the account accepted the current legal-document versions; false triggers the blocking consent gate.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
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
            'consent_current' => $this->consentService->hasCurrentConsent($user),
        ], 200);
    }

    /**
     * Get the authenticated user.
     *
     * consent_current tells the client whether the user has accepted the
     * current legal-document versions; false triggers the blocking consent
     * screen (Google signups, legacy accounts, version bumps).
     *
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get the authenticated user",
     *     description="Returns the current user and whether their legal-document consent is current.",
     *     operationId="me",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="The authenticated user",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="consent_current", type="boolean", example=true, description="False for Google signups, legacy accounts, and after a document version bump; triggers the consent gate.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Log out on this device",
     *     description="Revokes the current access token only; other sessions stay active.",
     *     operationId="logout",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logged out",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
