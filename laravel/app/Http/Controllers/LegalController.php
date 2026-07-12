<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigHelper;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    /**
     * Return the currently effective legal document versions.
     *
     * Public: the registration form and the consent gate need these before
     * the user is authenticated. Fails loudly (500) if a version is missing
     * from config — silently serving an empty version would corrupt the
     * consent audit trail keyed off these values.
     *
     * @OA\Get(
     *     path="/api/legal/versions",
     *     summary="Get current legal document versions",
     *     description="Returns the currently effective Terms of Service and Privacy Policy version identifiers.",
     *     operationId="getLegalVersions",
     *     tags={"Legal"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Current document versions",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="terms_version", type="string", example="2026-07-12"),
     *             @OA\Property(property="privacy_version", type="string", example="2026-07-12")
     *         )
     *     )
     * )
     */
    public function versions(): JsonResponse
    {
        return new JsonResponse([
            'terms_version' => ConfigHelper::getRequiredString('legal.terms_version'),
            'privacy_version' => ConfigHelper::getRequiredString('legal.privacy_version'),
        ]);
    }
}
