<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Services\ProfileAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileAggregationService $profileAggregationService
    ) {}

    /**
     * Return the authenticated user's profile with aggregated gameplay stats.
     *
     * @OA\Get(
     *     path="/api/profile",
     *     summary="Get authenticated user profile",
     *     description="Returns user profile data with aggregated gameplay statistics.",
     *     operationId="getUserProfile",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with profile data",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Profile")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $stats = $this->profileAggregationService->aggregate($user);

        return (new ProfileResource($user, $stats))->response();
    }
}
