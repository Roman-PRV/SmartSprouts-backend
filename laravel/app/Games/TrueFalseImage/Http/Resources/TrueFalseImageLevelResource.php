<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseImageLevelResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="TrueFalseImage.Level",
     *     type="object",
     *     title="TrueFalseImage.Level",
     *     description="True/False Image Level resource",
     *
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="Level ID"
     *     ),
     *     @OA\Property(
     *         property="title",
     *         type="string",
     *         description="The level title"
     *     ),
     *     @OA\Property(
     *         property="image_url",
     *         type="string",
     *         description="The level image URL"
     *     ),
     *     @OA\Property(
     *         property="statements",
     *         type="array",
     *         description="The level statements",
     *
     *         @OA\Items(ref="#/components/schemas/TrueFalseImage.Statement")
     *     )
     * )
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
            'title' => $level->title,
            'image_url' => $level->image_url,
            'statements' => TrueFalseImageStatementResource::collection($this->whenLoaded('statements')),
        ];
    }
}
