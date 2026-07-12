<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptConsentRequest;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;

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
     *             @OA\Property(property="consent_current", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Consent recorded",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="consent_current", type="boolean", example=true)
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

        $isCurrent = $this->consentService->hasCurrentConsent($user);

        if (! $isCurrent) {
            $this->consentService->recordAcceptance($user, $request->ip(), $request->userAgent());
        }

        return new JsonResponse([
            'consent_current' => true,
        ], $isCurrent ? 200 : 201);
    }
}
