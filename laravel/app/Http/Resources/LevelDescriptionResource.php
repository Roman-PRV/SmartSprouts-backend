<?php

namespace App\Http\Resources;

use App\Enums\LevelProgressStatus;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Player-facing level summary. `progress` is expected to be populated upstream by
 * LevelProgressService::decorate(); when it is not (e.g. the undecorated
 * `resources_default` show fallback) the field safely degrades to `not_started`
 * — i.e. "no progress data known", never a played status.
 *
 * @OA\Schema(
 *   schema="LevelDescriptionCollection",
 *   type="array",
 *
 *   @OA\Items(ref="#/components/schemas/LevelDescription")
 * )
 */
class LevelDescriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @OA\Schema(
     *     schema="LevelDescription",
     *     type="object",
     *     title="LevelDescription",
     *     description="Level summary for the player level grid",
     *
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="title", type="string", example="Level 1"),
     *     @OA\Property(property="image_url", type="string", format="uri"),
     *     @OA\Property(property="items_count", type="integer", description="Present on list endpoint"),
     *     @OA\Property(
     *         property="progress",
     *         type="string",
     *         enum={"mastered", "not_perfect", "not_started"},
     *         description="Player's progress from their most recent attempt on this level"
     *     )
     * )
     *
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {

        /** @var Level $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title,
            'image_url' => $level->image_url,
            'items_count' => $this->whenCounted('items'),
            'progress' => $level->progress ?? LevelProgressStatus::NOT_STARTED->value,
        ];
    }
}
