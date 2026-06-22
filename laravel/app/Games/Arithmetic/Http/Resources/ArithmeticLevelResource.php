<?php

namespace App\Games\Arithmetic\Http\Resources;

use App\Games\Arithmetic\Models\ArithmeticLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read shape for a single arithmetic level (the show endpoint). `operator` is
 * always present here; `equations` appears only when the level was loaded with
 * them, and omits the per-equation operator since it is already carried at the
 * level.
 *
 * Note: the generic levels-list endpoint renders through LevelDescriptionResource,
 * not this resource, so `operator` is in practice a show-only field.
 *
 * @OA\Schema(
 *     schema="Arithmetic.Level",
 *     type="object",
 *     title="Arithmetic.Level",
 *     description="Arithmetic game level (multiplication, addition, …).",
 *     required={"id", "title", "image_url", "operator"},
 *
 *     @OA\Property(property="id", type="integer", example=3, description="Level ID (1..10)"),
 *     @OA\Property(property="title", type="string", example="Multiply by 3", description="Localized level title"),
 *     @OA\Property(property="title_audio_url", type="string", format="uri", nullable=true, example=null, description="Title audio URL (not yet generated for arithmetic)"),
 *     @OA\Property(property="image_url", type="string", format="uri", example="https://example.com/storage/icons/default-icon.png", description="Level cover image URL"),
 *     @OA\Property(property="operator", type="string", example="×", description="Operation symbol for the level"),
 *     @OA\Property(
 *         property="equations",
 *         type="array",
 *         description="Facts for the level; present only on the single-level view.",
 *
 *         @OA\Items(
 *             type="object",
 *             required={"id", "operand_a", "operand_b", "result"},
 *
 *             @OA\Property(property="id", type="integer", example=4),
 *             @OA\Property(property="operand_a", type="integer", example=3),
 *             @OA\Property(property="operand_b", type="integer", example=4),
 *             @OA\Property(property="result", type="integer", example=12)
 *         )
 *     )
 * )
 */
class ArithmeticLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ArithmeticLevel $level */
        $level = $this->resource;

        return [
            'id' => $level->id,
            'title' => $level->title,
            'title_audio_url' => null,
            'image_url' => $level->image_url,
            'operator' => $level->operator,
            'equations' => $this->when(
                ! empty($level->equations),
                fn (): array => array_map(
                    static fn (array $equation): array => [
                        'id' => $equation['id'],
                        'operand_a' => $equation['operand_a'],
                        'operand_b' => $equation['operand_b'],
                        'result' => $equation['result'],
                    ],
                    $level->equations,
                ),
            ),
        ];
    }
}
