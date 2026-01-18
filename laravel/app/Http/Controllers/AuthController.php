<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        /** @var array{
         *     name: string,
         *     email: string,
         *     password: string,
         * } $data */
        $data = $request->validated();

        /** @var User $user */
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

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
            return new JsonResponse(['message' => 'Invalid credentials'], 401);
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
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Log out the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $user->tokens()->delete();
        }

        return new JsonResponse(['message' => 'Successfully logged out'], 200);
    }
}
