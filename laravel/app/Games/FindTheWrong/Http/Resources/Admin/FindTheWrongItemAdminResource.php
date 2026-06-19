<?php

namespace App\Games\FindTheWrong\Http\Resources\Admin;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-side shape for a FindTheWrongItem. Emits full translations for
 * editable text fields and their TTS audio paths (so the admin form can edit
 * and preview every locale). Polygon is forwarded as-is — already normalized
 * to 0..1 by StoreItemRequest / UpdateItemRequest validation.
 */
class FindTheWrongItemAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FindTheWrongItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'level_id' => $item->level_id,
            'polygon' => $item->polygon,
            'name' => $item->getTranslations('name'),
            'explanation' => $item->getTranslations('explanation'),
            'name_audio_url' => $item->getTranslations('name_audio_url'),
            'explanation_audio_url' => $item->getTranslations('explanation_audio_url'),
        ];
    }
}
