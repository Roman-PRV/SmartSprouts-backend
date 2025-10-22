<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TrueFalseImage.Level",
 *     type="object",
 *     description="Represents a game level with an image and a set of true/false statements",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Unique identifier of the level"),
 *     @OA\Property(property="title", type="string", example="Animals", description="Title of the level"),
 *     @OA\Property(property="image_url", type="string", format="url", example="https://example.com/image.jpg", description="URL of the image associated with the level"),
 *     @OA\Property(
 *         property="statements",
 *         type="array",
 *         description="List of statements related to the image",
 *
 *         @OA\Items(ref="#/components/schemas/TrueFalseImage.Statement")
 *     )
 * )
 *
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
