<?php

namespace App\Games\TrueFalseText\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TrueFalseText.Result",
 *     type="object",
 *     description="Validation result for a single statement in the True/False Text game",
 *
 *     @OA\Property(property="statement_id", type="integer", example=10, description="ID of the evaluated statement"),
 *     @OA\Property(property="correct", type="boolean", example=true, description="Whether the player's answer was correct"),
 *     @OA\Property(property="is_true", type="boolean", example=true, description="The actual truth value of the statement"),
 *     @OA\Property(property="explanation", type="string", example="Cats have pointy ears", description="Explanation for the correct answer"),
 *     @OA\Property(property="statement_audio_url", type="string", format="uri", nullable=true, description="Audio URL for the statement"),
 *     @OA\Property(property="explanation_audio_url", type="string", format="uri", nullable=true, description="Audio URL for the explanation")
 * )
 */
class TrueFalseTextResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'statement_id' => $this['statement_id'],
            'correct' => $this['correct'],
            'is_true' => $this['is_true'],
            'explanation' => $this['explanation'],
            'statement_audio_url' => $this['statement_audio_url'] ?? null,
            'explanation_audio_url' => $this['explanation_audio_url'] ?? null,
        ];
    }
}
