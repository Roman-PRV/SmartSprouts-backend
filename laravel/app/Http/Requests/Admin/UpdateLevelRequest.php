<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates an admin "update level" request. Same shape as StoreLevelRequest
 * except the image is optional — when absent, the existing image stays.
 *
 * @OA\Schema(
 *     schema="Admin.UpdateLevelRequest",
 *     type="object",
 *     title="Admin Update Level Request",
 *     description="Multipart/form-data payload for updating an existing game level. Send via POST with _method=PATCH so the file part survives Laravel's form-method spoofing.",
 *     required={"title"},
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
 *         nullable=true,
 *         description="Optional replacement cover image (jpeg/png/webp, max 5 MB). Omit to keep the existing one."
 *     )
 * )
 */
class UpdateLevelRequest extends FormRequest
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
            'title' => 'required|array',
            'title.uk' => 'required|string|max:255',
            'title.en' => 'required|string|max:255',
            'title.es' => 'required|string|max:255',
            'image' => 'nullable|file|mimes:jpeg,png,webp|max:5120',
        ];
    }

    /**
     * Convert validation failures into a JSON 422 instead of the default redirect.
     *
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
