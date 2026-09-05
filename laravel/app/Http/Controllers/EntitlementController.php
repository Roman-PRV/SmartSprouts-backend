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
     * @OA\Schema(
     *     schema="Entitlement.Limits",
     *     type="object",
     *     title="Daily allowances",
     *     description="null means the counter is not enforced — never a large number, so the client branches on it instead of comparing.",
     *
     *     @OA\Property(property="completed", type="integer", nullable=true, example=1),
     *     @OA\Property(property="started", type="integer", nullable=true, example=3)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement.Tier",
     *     type="object",
     *     title="Catalogue entry",
     *     description="Carries no display name: the client keys into its own billing translations by `key`.",
     *
     *     @OA\Property(property="key", type="string", enum={"free", "plus", "premium", "unlimited"}, example="free"),
     *     @OA\Property(property="limits", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="price_minor", type="integer", description="Monthly price in the payload's currency, minor units, net of VAT.", example=0)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement.Subscription",
     *     type="object",
     *     title="Subscription state",
     *
     *     @OA\Property(property="status", type="string", enum={"active", "cancelling", "past_due", "ended"}, example="active"),
     *     @OA\Property(property="current_period_end", type="string", format="date-time", description="End of the paid period. Only an active subscription renews on it — for the other statuses this is when access ends, or already ended.", example="2026-09-23T00:00:00Z"),
     *     @OA\Property(property="pending_tier", type="string", nullable=true, description="Set only while a downgrade is queued. It takes effect at current_period_end.", example="plus"),
     *     @OA\Property(property="cancel_at_period_end", type="boolean", example=false),
     *     @OA\Property(property="manage_url", type="string", nullable=true, description="The provider's billing portal. Null until a real purchase exists.", example=null)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement",
     *     type="object",
     *     title="Entitlement state",
     *
     *     @OA\Property(property="tier", type="string", enum={"free", "plus", "premium", "unlimited"}, description="`unlimited` for an exempt account too — they play identically.", example="free"),
     *     @OA\Property(property="is_exempt", type="boolean", description="Unlimited granted without payment. Suppresses every purchase entry point.", example=false),
     *     @OA\Property(property="limits", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="remaining", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="resets_at", type="string", format="date-time", description="UTC. Rendering it in the viewer's time is the client's job.", example="2026-08-24T00:00:00Z"),
     *     @OA\Property(property="purchasing_enabled", type="boolean", example=true),
     *     @OA\Property(property="subscription", ref="#/components/schemas/Entitlement.Subscription", nullable=true),
     *     @OA\Property(property="currency", type="string", description="One currency covers every price, so it sits beside the catalogue rather than inside each entry.", example="EUR"),
     *     @OA\Property(property="tiers", type="array", description="The whole catalogue in ladder order. The client holds no copy of allowances or prices.", @OA\Items(ref="#/components/schemas/Entitlement.Tier"))
     * )
     *
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

        return response()->json($this->snapshot->build($user), 200);
    }
}
