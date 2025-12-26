<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseImageStatementResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="TrueFalseImage.Statement",
     *     type="object",
     *     title="TrueFalseImage.Statement",
     *     description="True/False Image Statement resource",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="Statement ID"
     *     ),
     *     @OA\Property(
     *         property="level_id",
     *         type="integer",
     *         description="Level ID"
     *     ),
     *     @OA\Property(
     *         property="statement",
     *         type="string",
     *         description="The statement text"
     *     ),
     *     @OA\Property(
     *         property="explanation",
     *         type="string",
     *         description="Explanation for the statement"
     *     )
     * )
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
            'explanation' => $statement->explanation,
        ];
    }
}
