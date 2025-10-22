<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TrueFalseImage.Statement",
 *     type="object",
 *     description="A statement related to the image that must be evaluated as true or false",
 *
 *     @OA\Property(property="id", type="integer", example=10, description="Unique identifier of the statement"),
 *     @OA\Property(property="statement", type="string", example="This is a cat", description="Text of the statement to evaluate")
 * )
 *
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
