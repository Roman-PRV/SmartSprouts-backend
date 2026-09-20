<?php

namespace App\Http\Middleware;

use App\Enums\ErrorTypeEnum;
use App\Models\User;
use App\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses play while the account has not accepted the documents in force —
 * whether it declined them or never answered.
 *
 * Applied to the two play routes and nothing else. Reading the profile,
 * changing the password, deleting the account and answering the gate itself
 * stay open: that is how a restricted account exercises its data-subject
 * rights and gets back, and none of it may depend on accepting commercial
 * terms (FR-012a).
 *
 * Returns the refusal instead of throwing one. The daily-limit gates throw
 * because they refuse from inside a service, several layers below the
 * response; this check has the request in hand, so an exception class and a
 * renderable would buy nothing but two more files — and a throw would then
 * need excluding from the error report, since a fresh Terms version is an
 * ordinary state, not an incident.
 */
class EnsureConsentCurrent
{
    public function __construct(
        private ConsentService $consent,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->consent->hasCurrentConsent($user)) {
            return response()->json([
                'message' => __('exceptions.consent.not_current'),
                'error_type' => ErrorTypeEnum::CONSENT_REQUIRED->value,
            ], 403);
        }

        return $next($request);
    }
}
