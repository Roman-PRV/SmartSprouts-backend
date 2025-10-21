<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Games\TrueFalseImage\Models\TrueFalseImage
 */
class TrueFalseImageResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'statements' => TrueFalseImageStatementResource::collection($this->whenLoaded('statements')),
        ];
    }
}
