<?php

namespace App\Http\Requests\Admin;

use App\Traits\RespondsWithJsonValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "create item" request consumed by per-game admin
 * controllers (currently FindTheWrongItemController). Lives under the shared
 * App\Http\Requests\Admin namespace because the polygon shape rules apply to
 * any future game that uses polygon-targeted items; if a different shape
 * emerges (true/false flag, free text), that game ships its own request.
 *
 * Polygon coordinates are normalized 0..1 (relative to the level image),
 * enforced at this boundary so service code can trust the shape.
 *
 * @OA\Schema(
 *     schema="Admin.StoreItemRequest",
 *     type="object",
 *     title="Admin Store Item Request",
 *     description="JSON payload for creating a new polygon item under a level.",
 *     required={"polygon", "name"},
 *
 *     @OA\Property(
 *         property="polygon",
 *         type="array",
 *         minItems=3,
 *         description="Ordered list of [x, y] points in 0..1 coordinates. Minimum 3 points.",
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
 *         description="Localized item name. All three locales are required (used by TTS).",
 *         required={"uk", "en", "es"},
 *         @OA\Property(property="uk", type="string", example="Каструля"),
 *         @OA\Property(property="en", type="string", example="Pot"),
 *         @OA\Property(property="es", type="string", example="Olla")
 *     ),
 *     @OA\Property(
 *         property="explanation",
 *         type="object",
 *         description="Optional localized explanation shown after the item is found. Locales are independent — send only those you have.",
 *         @OA\Property(property="uk", type="string"),
 *         @OA\Property(property="en", type="string"),
 *         @OA\Property(property="es", type="string")
 *     )
 * )
 */
class StoreItemRequest extends FormRequest
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
            'polygon' => 'required|array|min:3',
            'polygon.*' => 'required|array|size:2',
            'polygon.*.0' => 'required|numeric|between:0,1',
            'polygon.*.1' => 'required|numeric|between:0,1',

            'name' => 'required|array',
            'name.uk' => 'required|string',
            'name.en' => 'required|string',
            'name.es' => 'required|string',

            'explanation' => 'sometimes|array',
            'explanation.uk' => 'sometimes|string',
            'explanation.en' => 'sometimes|string',
            'explanation.es' => 'sometimes|string',
        ];
    }
}
