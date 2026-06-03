<?php

namespace App\Games\FindTheWrong\Http\Resources;

use App\Facades\Tts;
use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reveal-after-submit shape for a find-the-wrong item. Used for both found and
 * missed items in the submit response — `stars` is included only when set
 * (found items 1..3); for missed items it's omitted.
 *
 * `stars` is injected via the second constructor argument so callers can
 * build the resource per item with the right rating, without contorting
 * `$this->resource` into a wrapper object.
 *
 * @OA\Schema(
 *     schema="FindTheWrong.RevealItem",
 *     type="object",
 *     title="FindTheWrong.RevealItem",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="stars", type="integer", minimum=1, maximum=3, nullable=true, description="Present only for found items"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="name_audio_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="explanation", type="string"),
 *     @OA\Property(property="explanation_audio_url", type="string", format="uri", nullable=true)
 * )
 */
class FindTheWrongRevealItemResource extends JsonResource
{
    public function __construct(FindTheWrongItem $item, public readonly ?int $stars = null)
    {
        parent::__construct($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FindTheWrongItem $item */
        $item = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $item->id,
            'stars' => $this->whenNotNull($this->stars),
            'name' => $item->name,
            'name_audio_url' => Tts::getOrGenerate(
                TtsAudioContext::make($item, 'name_audio_url', $locale)
            ),
            'explanation' => $item->explanation,
            'explanation_audio_url' => Tts::getOrGenerate(
                TtsAudioContext::make($item, 'explanation_audio_url', $locale)
            ),
        ];
    }
}
