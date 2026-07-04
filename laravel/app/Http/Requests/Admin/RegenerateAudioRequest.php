<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for a per-(field, locale) TTS regeneration request, shared by the level
 * and statement admin endpoints. Only the surface shape is validated here;
 * whether the field is a real audio attribute of the target model — and whether
 * the locale is supported — is the model-aware job of TtsRegenerationService,
 * which surfaces a 422 for unknown values.
 *
 * @OA\Schema(
 *     schema="Admin.RegenerateAudioRequest",
 *     type="object",
 *     title="Admin Regenerate Audio Request",
 *     description="Selects which audio field and locale to regenerate.",
 *     required={"field", "locale"},
 *
 *     @OA\Property(property="field", type="string", example="statement_audio_url"),
 *     @OA\Property(property="locale", type="string", example="en")
 * )
 */
class RegenerateAudioRequest extends FormRequest
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
            'field' => 'required|string',
            'locale' => 'required|string',
        ];
    }
}
