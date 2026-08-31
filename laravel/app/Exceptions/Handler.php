<?php

namespace App\Exceptions;

use App\Enums\ErrorTypeEnum;
use App\Exceptions\Entitlement\DailyLimitExceededException;
use App\Exceptions\Entitlement\LevelNotOpenedTodayException;
use App\Exceptions\Entitlement\SubscriptionBlocksExemptionException;
use App\Helpers\ConfigHelper;
use App\Services\Entitlement\DailyUsageService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * The exemption refusal is a business rule answered as a 409, not a failure:
     * if reported, every normal admin action against a paying account looks
     * like an incident. The two daily gates are the same kind of answer: a spent
     * allowance and a tab left open overnight are ordinary outcomes.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        SubscriptionBlocksExemptionException::class,
        DailyLimitExceededException::class,
        LevelNotOpenedTodayException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Cross-cutting domain → HTTP mapping, so the controllers don't repeat it
        // and the response shape is the same in every environment.
        // TableMissingException carries its own status; the RuntimeException-based
        // GameNotConfiguredException gets its 400 here. Other HttpExceptions
        // (e.g. NotFoundHttpException) keep the framework's default rendering.
        $this->renderable(function (TableMissingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        });

        $this->renderable(function (GameNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        });

        $this->renderable(function (SubscriptionBlocksExemptionException $e) {
            return response()->json([
                'message' => __('exceptions.entitlement.exemption_blocked_by_subscription'),
                'error_type' => ErrorTypeEnum::SUBSCRIPTION_STILL_GRANTS_TIER->value,
            ], 409);
        });

        // One registration for both gates: the exception subclass carries which
        // counter was spent, so the shape is written once.
        $this->renderable(function (DailyLimitExceededException $e) {
            return response()->json([
                'message' => __('exceptions.entitlement.daily_limit_reached'),
                'error_type' => ErrorTypeEnum::LEVEL_LIMIT_REACHED->value,
                'details' => [
                    'limit_kind' => $e->limitKind()->value,
                    'resets_at' => DailyUsageService::resetsAt()->toIso8601ZuluString(),
                    'purchasing_enabled' => ConfigHelper::getBool('billing.purchasing_enabled'),
                ],
            ], 403);
        });

        // Separate on purpose: a missing precondition, not a spent allowance.
        $this->renderable(function (LevelNotOpenedTodayException $e) {
            return response()->json([
                'message' => __('exceptions.entitlement.level_not_opened_today'),
                'error_type' => ErrorTypeEnum::LEVEL_NOT_OPENED_TODAY->value,
            ], 403);
        });
    }

    /**
     * API-only backend: render every exception as JSON regardless of the
     * request's Accept header (there is no web UI to redirect to).
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return true;
    }

    /**
     * Single, stable JSON 422 shape for every validation failure (game submits
     * via Validator, FormRequests, and ValidationException::withMessages).
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => __('validation.failed_message'),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * API-only backend: always return a JSON 401, regardless of the request's
     * Accept header, instead of redirecting to a non-existent `login` route.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], 401);
    }
}
