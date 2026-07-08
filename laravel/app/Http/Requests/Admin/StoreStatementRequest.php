<?php

namespace App\Http\Requests\Admin;

use App\Rules\SupportedLocaleKeys;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "create statement" request, shared by both true/false
 * games (the statement shape is identical). `statement` is required in every
 * locale (it drives TTS); `explanation` is optional and per-locale independent.
 *
 * @OA\Schema(
 *     schema="Admin.StoreStatementRequest",
 *     type="object",
 *     title="Admin Store Statement Request",
 *     required={"statement", "is_true"},
 *
 *     @OA\Property(
 *         property="statement",
 *         type="object",
 *         required={"uk", "en", "es"},
 *         @OA\Property(property="uk", type="string"),
 *         @OA\Property(property="en", type="string"),
 *         @OA\Property(property="es", type="string")
 *     ),
 *     @OA\Property(
 *         property="explanation",
 *         type="object",
 *         @OA\Property(property="uk", type="string"),
 *         @OA\Property(property="en", type="string"),
 *         @OA\Property(property="es", type="string")
 *     ),
 *     @OA\Property(property="is_true", type="boolean", example=true)
 * )
 */
class StoreStatementRequest extends FormRequest
{
    /**
     * Authorization is delegated to the route middleware (auth + EnsureAdmin).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statement' => ['required', 'array', new SupportedLocaleKeys],
            'statement.uk' => 'required|string',
            'statement.en' => 'required|string',
            'statement.es' => 'required|string',

            'explanation' => ['sometimes', 'array', new SupportedLocaleKeys],
            'explanation.uk' => 'sometimes|string',
            'explanation.en' => 'sometimes|string',
            'explanation.es' => 'sometimes|string',

            'is_true' => 'required|boolean',
        ];
    }
}
