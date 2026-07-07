<?php

namespace App\Exceptions;

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
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
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

        // Cross-cutting domain → HTTP mapping for these two exceptions, so the
        // controllers don't repeat it and the response stays a clean {message} in
        // every environment. TableMissingException carries its own status; the
        // RuntimeException-based GameNotConfiguredException gets its 400 here. Other
        // HttpExceptions (e.g. NotFoundHttpException) keep the framework's default
        // rendering.
        $this->renderable(function (TableMissingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        });

        $this->renderable(function (GameNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
