<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ProfileStats",
 *     type="object",
 *     title="Profile Statistics",
 *
 *     @OA\Property(property="totalScore", type="integer", example=1250),
 *     @OA\Property(property="totalLevels", type="integer", example=45),
 *     @OA\Property(property="completedLevels", type="integer", example=12),
 *     @OA\Property(property="correctAnswersPercentage", type="number", format="float", example=88.5)
 * )
 *
 * @OA\Schema(
 *     schema="Profile",
 *     type="object",
 *     title="User Profile",
 *     required={"name", "email", "stats"},
 *
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="stats", ref="#/components/schemas/ProfileStats")
 * )
 */
class ProfileResource extends JsonResource
{
    /**
     * @param array{
     *     total_score: int,
     *     completed_levels: int,
     *     total_levels: int,
     *     correct_answers_percentage: float,
     * } $stats
     */
    public function __construct(User $user, private readonly array $stats)
    {
        parent::__construct($user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'stats' => [
                'totalScore' => $this->stats['total_score'],
                'totalLevels' => $this->stats['total_levels'],
                'completedLevels' => $this->stats['completed_levels'],
                'correctAnswersPercentage' => $this->stats['correct_answers_percentage'],
            ],
        ];
    }
}
