<?php

namespace App\Games\TrueFalseText\Http\Resources;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseTextLevelResource extends JsonResource
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

        /** @var TrueFalseTextLevel $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title,
            'text' => $level->text,
            'statements' => TrueFalseTextStatementResource::collection($this->whenLoaded('statements')),
        ];
    }
}
