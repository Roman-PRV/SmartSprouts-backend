<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Games\TrueFalseImage\Models\TrueFalseImageStatement
 */
class TrueFalseImageStatementResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'statements' => TrueFalseImageStatementResource::collection($this->whenLoaded('statements')),
        ];
    }
}
