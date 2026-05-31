<?php

namespace App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Force JSON 422 on validation failure regardless of the request's Accept header.
 * Applied to FormRequests in API contexts where redirects are never desired —
 * keeps the failure payload identical to the rest of the API surface.
 */
trait RespondsWithJsonValidation
{
    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => __('validation.failed_message'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
