<?php

namespace App\Games\TrueFalseText\Http\Resources;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseTextLevelResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="TrueFalseText.Level",
     *     type="object",
     *     title="TrueFalseText.Level",
     *     description="True/False Text Level resource",
     *     required={"id", "title", "image_url", "text", "statements"},
     *
     *     @OA\Property(property="id", type="integer", example=1, description="Level ID"),
     *     @OA\Property(property="title", type="string", example="Level 1", description="Level title"),
     *     @OA\Property(property="image_url", type="string", example="https://example.com/image.png", description="Level image URL"),
     *     @OA\Property(property="text", type="string", example="Some introductory text", description="Level text"),
     *     @OA\Property(
     *         property="statements",
     *         type="array",
     *
     *         @OA\Items(ref="#/components/schemas/TrueFalseText.Statement")
     *     )
     * )
     */
    public function toArray(Request $request): array
    {

        /** @var TrueFalseTextLevel $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title,
            'image_url' => $level->image_url,
            'text' => $level->text,
            'statements' => TrueFalseTextStatementResource::collection($this->whenLoaded('statements')),
        ];
    }
}
