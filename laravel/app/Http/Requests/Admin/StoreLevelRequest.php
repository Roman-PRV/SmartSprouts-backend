<?php

namespace App\Http\Requests\Admin;

use App\Traits\RespondsWithJsonValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "create level" request for any game whose admin operations
 * are dispatched via App\Http\Controllers\Admin\LevelController.
 *
 * @OA\Schema(
 *     schema="Admin.StoreLevelRequest",
 *     type="object",
 *     title="Admin Store Level Request",
 *     description="Multipart/form-data payload for creating a new game level.",
 *     required={"title", "image"},
 *
 *     @OA\Property(
 *         property="title",
 *         type="object",
 *         description="Localized level title. All three locales are required.",
 *         required={"uk", "en", "es"},
 *         @OA\Property(property="uk", type="string", maxLength=255, example="Кухня"),
 *         @OA\Property(property="en", type="string", maxLength=255, example="Kitchen"),
 *         @OA\Property(property="es", type="string", maxLength=255, example="Cocina")
 *     ),
 *     @OA\Property(
 *         property="image",
 *         type="string",
 *         format="binary",
 *         description="Cover image (jpeg/png/webp, max 5 MB)."
 *     )
 * )
 */
class StoreLevelRequest extends FormRequest
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
            'title' => 'required|array',
            'title.uk' => 'required|string|max:255',
            'title.en' => 'required|string|max:255',
            'title.es' => 'required|string|max:255',
            'image' => 'required|file|mimes:jpeg,png,webp|max:5120',
        ];
    }
}
