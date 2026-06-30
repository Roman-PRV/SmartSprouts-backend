<?php

namespace App\Http\Requests\Admin;

use App\Models\Game;
use App\Rules\SupportedLocaleKeys;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin "create level" request for any game dispatched via
 * App\Http\Controllers\Admin\LevelController.
 *
 * Game-aware by design: most games take a required cover image, while the
 * TrueFalseText game instead carries a translatable `text` body with an
 * optional image. Branching the rules here (rather than per-game requests) is
 * what lets every game share the one slug-free admin/games/{game}/levels URL.
 */
class StoreLevelRequest extends FormRequest
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
     *         description="Cover image (jpeg/png/webp, max 5 MB). Required except for TrueFalseText."
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
        ];

        if ($this->routeGamePrefix() === 'true_false_text') {
            return $rules + [
                'text' => ['required', 'array', new SupportedLocaleKeys],
                'text.uk' => 'required|string',
                'text.en' => 'required|string',
                'text.es' => 'required|string',
                'image' => 'nullable|file|mimes:jpeg,png,webp|max:5120',
            ];
        }

        return $rules + [
            'image' => 'required|file|mimes:jpeg,png,webp|max:5120',
        ];
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
