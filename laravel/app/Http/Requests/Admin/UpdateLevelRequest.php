<?php

namespace App\Http\Requests\Admin;

use App\Models\Game;
use App\Rules\SupportedLocaleKeys;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "update level" request. Same game-aware shape as
 * StoreLevelRequest, but the image is always optional on update — when absent,
 * the existing image stays.
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
     *         property="text",
     *         type="object",
     *         description="Localized body text (TrueFalseText only; required there).",
     *         @OA\Property(property="uk", type="string"),
     *         @OA\Property(property="en", type="string"),
     *         @OA\Property(property="es", type="string")
     *     ),
     *     @OA\Property(
     *         property="image",
     *         type="string",
     *         format="binary",
     *         nullable=true,
     *         description="Optional replacement cover image (jpeg/png/webp, max 5 MB). Omit to keep the existing one."
     *     )
     * )
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'array', new SupportedLocaleKeys],
            'title.uk' => 'required|string|max:255',
            'title.en' => 'required|string|max:255',
            'title.es' => 'required|string|max:255',
            'image' => 'nullable|file|mimes:jpeg,png,webp|max:5120',
        ];

        if ($this->routeGamePrefix() === 'true_false_text') {
            return $rules + [
                'text' => ['required', 'array', new SupportedLocaleKeys],
                'text.uk' => 'required|string',
                'text.en' => 'required|string',
                'text.es' => 'required|string',
            ];
        }

        return $rules;
    }

    /**
     * The table_prefix of the route-bound game, or null when not resolvable.
     */
    private function routeGamePrefix(): ?string
    {
        $game = $this->route('game');

        return $game instanceof Game ? $game->table_prefix : null;
    }
}
