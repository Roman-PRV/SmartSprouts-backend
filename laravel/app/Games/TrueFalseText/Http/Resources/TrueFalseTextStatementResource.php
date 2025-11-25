<?php

namespace App\Games\TrueFalseText\Http\Resources;

use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseTextStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {

        /** @var TrueFalseTextStatement $statement */
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
