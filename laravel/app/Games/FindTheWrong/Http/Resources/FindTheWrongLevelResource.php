<?php

namespace App\Games\FindTheWrong\Http\Resources;

use App\Facades\Tts;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindTheWrongLevelResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="FindTheWrong.Level",
     *     type="object",
     *     title="FindTheWrong.Level",
     *     description="Find the wrong level resource",
     *
     *     @OA\Property(property="id", type="integer", description="Level ID"),
     *     @OA\Property(property="title", type="string", description="Localized level title"),
     *     @OA\Property(
     *         property="title_audio_url",
     *         type="string",
     *         format="uri",
     *         nullable=true,
     *         description="Absolute URL of the title audio for the current locale"
     *     ),
     *     @OA\Property(
     *         property="image_url",
     *         type="string",
     *         format="uri",
     *         nullable=true,
     *         description="Absolute URL of the level image"
     *     ),
     *     @OA\Property(
     *         property="items_count",
     *         type="integer",
     *         description="Number of items on the level (present on list endpoint)"
     *     ),
     *     @OA\Property(
     *         property="items",
     *         type="array",
     *         description="Polygon items (present on show endpoint)",
     *
     *         @OA\Items(ref="#/components/schemas/FindTheWrong.Item")
     *     )
     * )
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FindTheWrongLevel $level */
        $level = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $level->id,
            'title' => $level->title,
            'title_audio_url' => Tts::getOrGenerate(
                TtsAudioContext::make($level, 'title_audio_url', $locale)
            ),
            'image_url' => $level->image_url,
            'items_count' => $this->whenCounted('items'),
            'items' => FindTheWrongItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
