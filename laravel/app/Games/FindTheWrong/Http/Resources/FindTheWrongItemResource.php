<?php

namespace App\Games\FindTheWrong\Http\Resources;

use App\Facades\Tts;
use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindTheWrongItemResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="FindTheWrong.Item",
     *     type="object",
     *     title="FindTheWrong.Item",
     *     description="Public-facing polygon item without reveal-after-submit fields",
     *
     *     @OA\Property(property="id", type="integer", description="Item ID"),
     *     @OA\Property(
     *         property="polygon",
     *         type="array",
     *         description="Ordered list of [x, y] vertices in normalized 0..1 coordinates",
     *
     *         @OA\Items(
     *             type="array",
     *             @OA\Items(type="number", format="float")
     *         )
     *     ),
     *
     *     @OA\Property(property="name", type="string", description="Localized item name"),
     *     @OA\Property(
     *         property="name_audio_url",
     *         type="string",
     *         format="uri",
     *         nullable=true,
     *         description="Absolute URL of the name audio for the current locale"
     *     )
     * )
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FindTheWrongItem $item */
        $item = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $item->id,
            'polygon' => $item->polygon,
            'name' => $item->name,
            'name_audio_url' => Tts::getOrGenerate(
                TtsAudioContext::make($item, 'name_audio_url', $locale)
            ),
        ];
    }
}
