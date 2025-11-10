<?php

namespace App\Http\Resources;

use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelDescriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {

        /** @var Level $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title,
            'image_url' => $level->imageUrl,
        ];
    }
}
