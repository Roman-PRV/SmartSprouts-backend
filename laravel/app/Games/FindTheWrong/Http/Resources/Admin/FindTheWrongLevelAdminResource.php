<?php

namespace App\Games\FindTheWrong\Http\Resources\Admin;

use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-side shape for a single FindTheWrongLevel. Returns full translations
 * for editable fields and embeds all items so the admin form can display and
 * edit the level's content in one request.
 */
class FindTheWrongLevelAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FindTheWrongLevel $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->getTranslations('title'),
            'image_url' => $level->image_url,
            'items' => FindTheWrongItemAdminResource::collection($level->items),
        ];
    }
}
