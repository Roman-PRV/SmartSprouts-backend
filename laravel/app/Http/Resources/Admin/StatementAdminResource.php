<?php

namespace App\Http\Resources\Admin;

use App\Contracts\TranslatableStatementInterface;
use App\Contracts\TtsAudioInterface;
use App\Services\Tts\TtsAudioStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin shape for a true/false statement, shared by both games (the statement
 * form is identical across them). Emits the full translations map for the
 * editable text fields plus a per-locale `{ url, is_stale }` audio block for
 * each TTS field, so the form can edit every locale and light up the
 * regenerate button without recomputing any hash on the client.
 *
 * Reads are side-effect free: audio status comes from MediaUrlGenerator +
 * the stored path hash, never from Tts::getOrGenerate.
 *
 * @OA\Schema(
 *     schema="Admin.Statement",
 *     type="object",
 *     title="Admin Statement",
 *     description="Admin-side true/false statement with localized text and per-locale audio status.",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="level_id", type="integer", example=1),
 *     @OA\Property(property="is_true", type="boolean", example=true),
 *     @OA\Property(property="statement", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(property="explanation", type="object", @OA\AdditionalProperties(type="string")),
 *     @OA\Property(
 *         property="statement_audio",
 *         type="object",
 *
 *         @OA\AdditionalProperties(ref="#/components/schemas/Admin.AudioStatus")
 *     ),
 *
 *     @OA\Property(
 *         property="explanation_audio",
 *         type="object",
 *
 *         @OA\AdditionalProperties(ref="#/components/schemas/Admin.AudioStatus")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Admin.AudioStatus",
 *     type="object",
 *     title="Admin Audio Status",
 *     description="Per-locale TTS audio status.",
 *
 *     @OA\Property(property="url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="is_stale", type="boolean", example=false)
 * )
 */
class StatementAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Model&TranslatableStatementInterface&TtsAudioInterface $statement */
        $statement = $this->resource;
        $status = app(TtsAudioStatusService::class);

        return [
            'id' => $statement->getKey(),
            'level_id' => $statement->getAttribute('level_id'),
            'is_true' => (bool) $statement->getAttribute('is_true'),
            'statement' => $statement->getTranslations('statement'),
            'explanation' => $statement->getTranslations('explanation'),
            'statement_audio' => $status->mapFor($statement, 'statement_audio_url'),
            'explanation_audio' => $status->mapFor($statement, 'explanation_audio_url'),
        ];
    }
}
