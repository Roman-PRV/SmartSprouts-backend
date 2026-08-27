<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Entitlement\ExemptionReasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExemptionRequest;
use App\Http\Resources\Admin\ExemptionAdminResource;
use App\Models\User;
use App\Services\Entitlement\ExemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The admin surface for unlimited access granted without payment.
 */
class ExemptionController extends Controller
{
    public function __construct(
        private readonly ExemptionService $exemptions,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/admin/exemptions",
     *     summary="List accounts holding unlimited access without paying",
     *     description="Accounts on the paid Unlimited tier are absent: they are customers, not exemptions, and both play without limits, so this listing is the only place the difference is visible.",
     *     operationId="adminExemptionIndex",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="The current grants",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Admin.Exemption"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin privileges required")
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        return ExemptionAdminResource::collection($this->exemptions->list());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/exemptions",
     *     summary="Grant unlimited access without payment",
     *     operationId="adminExemptionStore",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Admin.StoreExemptionRequest")),
     *
     *     @OA\Response(response=201, description="Granted, or an existing grant restated under a new reason", @OA\JsonContent(ref="#/components/schemas/Admin.Exemption")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin privileges required"),
     *     @OA\Response(response=409, description="The account holds a subscription that still grants a tier"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function store(StoreExemptionRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        /** @var string|null $note */
        $note = $request->validated('note');

        $exemption = $this->exemptions->grant(
            User::query()->findOrFail($request->validated('user_id')),
            $request->safe()->enum('reason', ExemptionReasonEnum::class),
            $note,
            $admin,
        );

        return ExemptionAdminResource::make($exemption->load(['user', 'grantedBy']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/exemptions/{user}",
     *     summary="Revoke unlimited access",
     *     operationId="adminExemptionDestroy",
     *     tags={"Admin"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=204, description="Revoked"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Admin privileges required"),
     *     @OA\Response(response=404, description="The account holds no exemption")
     * )
     */
    public function destroy(User $user): Response
    {
        if (! $this->exemptions->revoke($user)) {
            abort(Response::HTTP_NOT_FOUND, __('exceptions.entitlement.exemption_not_found'));
        }

        return response()->noContent();
    }
}
