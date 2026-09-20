<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptConsentRequest;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConsentController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    /**
     * Record acceptance of the current legal-document versions.
     *
     * Repairs the consent gate for accounts created without consent (Google
     * OAuth, legacy users) and captures re-consent after a version bump.
     * Idempotent: a repeated call with consent already current writes nothing
     * (re-logging the same version adds no evidentiary value).
     *
     * @OA\Post(
     *     path="/api/profile/consents",
     *     summary="Accept the current legal documents",
     *     description="Records a versioned terms + privacy acceptance for the authenticated user. No-op when consent is already current.",
     *     operationId="acceptConsents",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"accepted_terms"},
     *
     *             @OA\Property(property="accepted_terms", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Consent was already current; nothing recorded",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="consent_current", type="boolean", example=true),
     *             @OA\Property(property="consent_declined", type="boolean", example=false, description="Always false here: the acceptance just landed.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Consent recorded",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="consent_current", type="boolean", example=true),
     *             @OA\Property(property="consent_declined", type="boolean", example=false, description="Always false here: the acceptance just landed.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Affirmation checkbox not accepted")
     * )
     */
    public function store(AcceptConsentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recorded = $this->consentService->recordAcceptance($user, $request->ip(), $request->userAgent());

        return new JsonResponse([
            // Literal values: the acceptance just landed.
            'consent_current' => true,
            'consent_declined' => false,
        ], $recorded ? 201 : 200);
    }

    /**
     * Record refusal of the current legal-document versions.
     *
     * Leaves the account restricted rather than locked out: play is refused
     * while the profile, password change and account deletion stay reachable,
     * so data-subject rights never depend on accepting commercial terms.
     * Reversible — accepting later through store() restores full access.
     *
     * @OA\Post(
     *     path="/api/profile/consents/decline",
     *     summary="Decline the current legal documents",
     *     description="Records a versioned refusal for the authenticated user, leaving the account in a restricted state. Idempotent.",
     *     operationId="declineConsents",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=204, description="Refusal recorded, or already on record"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function decline(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->consentService->recordDecline($user);

        return response()->noContent();
    }
}
