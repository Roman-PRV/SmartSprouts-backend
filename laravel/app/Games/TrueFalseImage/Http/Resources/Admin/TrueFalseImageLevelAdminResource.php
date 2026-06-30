<?php

namespace App\Games\TrueFalseImage\Http\Resources\Admin;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Http\Resources\Admin\StatementAdminResource;
use App\Services\Tts\TtsAudioStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin editor shape for a TrueFalseImage level: full title translations, the
 * cover image URL, per-locale title audio status, and the embedded statements
 * (each via the shared StatementAdminResource). Used by the single-level read
 * endpoint; the levels list uses the generic LevelAdminResource.
 *
 * @OA\Schema(
 *     schema="Admin.TrueFalseImage.Level",
 *     type="object",
 *     title="Admin TrueFalseImage Level",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(property="image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(
 *         property="title_audio",
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
class TrueFalseImageLevelAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TrueFalseImageLevel $level */
        $level = $this->resource;
        $status = app(TtsAudioStatusService::class);

        return [
            'id' => $level->id,
            'title' => $level->getTranslations('title'),
            'image_url' => $level->image_url,
            'title_audio' => $status->mapFor($level, 'title_audio_url'),
            'statements' => StatementAdminResource::collection($level->statements),
        ];
    }
}
