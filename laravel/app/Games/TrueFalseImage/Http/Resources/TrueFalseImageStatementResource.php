<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseImageStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {

        /** @var TrueFalseImageStatement $statement */
        $statement = $this->resource;

        return [
            'id' => $statement->id,
            'level_id' => $statement->level_id,
            'statement' => $statement->statement,
            'is_true' => $statement->is_true,
            'explanation' => $statement->explanation,
        ];
    }
}
