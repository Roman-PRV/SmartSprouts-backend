<?php

namespace App\Http\Requests\Admin;

use App\Traits\RespondsWithJsonValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "update item" request consumed by per-game admin
 * controllers (currently FindTheWrongItemController). All fields are
 * optional — a PATCH with only `polygon` keeps name/explanation, a PATCH
 * with only a single locale of `name` updates just that locale (Spatie
 * translatable semantics).
 *
 * When `polygon` IS provided it must still satisfy the full shape rules
 * (≥3 points, [x, y] pairs in 0..1).
 *
 * @OA\Schema(
 *     schema="Admin.UpdateItemRequest",
 *     type="object",
 *     title="Admin Update Item Request",
 *     description="JSON payload for partially updating an item.",
 *
 *     @OA\Property(
 *         property="polygon",
 *         type="array",
 *         minItems=3,
 *         description="Full replacement polygon (no partial merge). Same shape rules as create.",
 *
 *         @OA\Items(
 *             type="array",
 *             minItems=2,
 *             maxItems=2,
 *             @OA\Items(type="number", format="float", minimum=0, maximum=1)
 *         )
 *     ),
 *
 *     @OA\Property(
 *         property="name",
 *         type="object",
 *         description="Locale keys to overwrite. Omitted locales are kept as-is.",
 *         @OA\Property(property="uk", type="string", maxLength=255),
 *         @OA\Property(property="en", type="string", maxLength=255),
 *         @OA\Property(property="es", type="string", maxLength=255)
 *     ),
 *     @OA\Property(
 *         property="explanation",
 *         type="object",
 *         @OA\Property(property="uk", type="string", maxLength=2000),
 *         @OA\Property(property="en", type="string", maxLength=2000),
 *         @OA\Property(property="es", type="string", maxLength=2000)
 *     )
 * )
 */
class UpdateItemRequest extends FormRequest
{
    use RespondsWithJsonValidation;

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
            'polygon' => 'sometimes|array|min:3',
            'polygon.*' => 'required_with:polygon|array|size:2',
            'polygon.*.0' => 'required_with:polygon|numeric|between:0,1',
            'polygon.*.1' => 'required_with:polygon|numeric|between:0,1',

            'name' => 'sometimes|array',
            'name.uk' => 'sometimes|string',
            'name.en' => 'sometimes|string',
            'name.es' => 'sometimes|string',

            'explanation' => 'sometimes|array',
            'explanation.uk' => 'sometimes|string',
            'explanation.en' => 'sometimes|string',
            'explanation.es' => 'sometimes|string',
        ];
    }
}
