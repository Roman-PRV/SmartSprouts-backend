<?php

namespace App\Games\TrueFalseText\Http\Resources\Admin;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Http\Resources\Admin\StatementAdminResource;
use App\Services\Tts\TtsAudioStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin editor shape for a TrueFalseText level: full title and text
 * translations, optional cover image URL, per-locale audio status for both the
 * title and the text body, and the embedded statements.
 *
 * @OA\Schema(
 *     schema="Admin.TrueFalseText.Level",
 *     type="object",
 *     title="Admin TrueFalseText Level",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(property="text", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(property="image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(
 *         property="title_audio",
 *         type="object",
 *
 *         @OA\AdditionalProperties(ref="#/components/schemas/Admin.AudioStatus")
 *     ),
 *
 *     @OA\Property(
 *         property="text_audio",
 *         type="object",
 *
 *         @OA\AdditionalProperties(ref="#/components/schemas/Admin.AudioStatus")
 *     ),
 *
 *     @OA\Property(
 *         property="statements",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Admin.Statement")
 *     )
 * )
 */
class TrueFalseTextLevelAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TrueFalseTextLevel $level */
        $level = $this->resource;
        $status = app(TtsAudioStatusService::class);

        return [
            'id' => $level->id,
            'title' => $level->getTranslations('title'),
            'text' => $level->getTranslations('text'),
            'image_url' => $level->image_url,
            'title_audio' => $status->mapFor($level, 'title_audio_url'),
            'text_audio' => $status->mapFor($level, 'text_audio_url'),
            'statements' => StatementAdminResource::collection($level->statements),
        ];
    }
}
