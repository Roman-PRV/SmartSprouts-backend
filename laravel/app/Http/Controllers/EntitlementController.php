<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Entitlement\EntitlementSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one endpoint the client reads tier state from.
 */
class EntitlementController extends Controller
{
    public function __construct(private readonly EntitlementSnapshotService $snapshot) {}

    /**
     * @OA\Get(
     *     path="/api/entitlement",
     *     summary="Get the current tier, both allowances and the tier catalogue",
     *     description="Called on app bootstrap and after any change to tier or usage. `remaining` is advisory for display — the server is authoritative, so a stale value must never gate the interface; let the request through and read the real answer.",
     *     operationId="getEntitlement",
     *     tags={"Entitlement"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="The account's entitlement state", @OA\JsonContent(ref="#/components/schemas/Entitlement")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->snapshot->for($user), 200);
    }
}
