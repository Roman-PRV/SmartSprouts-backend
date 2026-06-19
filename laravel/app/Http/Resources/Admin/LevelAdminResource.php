<?php

namespace App\Http\Resources\Admin;

use App\Contracts\TranslatableLevelInterface;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-side level shape. Returns the full translations map for `title` so the
 * admin form can edit every locale; the public LevelDescriptionResource emits
 * only the current locale's string.
 */
class LevelAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {

        /** @var Level&TranslatableLevelInterface $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->getTranslations('title'),
            'image_url' => $level->image_url,
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
