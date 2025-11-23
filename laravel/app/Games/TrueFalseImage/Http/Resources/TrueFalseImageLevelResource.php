<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseImageLevelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Exposes level fields and nested statements (if loaded).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {

        /** @var TrueFalseImageLevel $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title ?? null,
            'image_url' => $level->image_url ?? null,
            'statements' => TrueFalseImageStatementResource::collection($this->whenLoaded('statements', $level->statements ?? [])),
        ];
    }
}
