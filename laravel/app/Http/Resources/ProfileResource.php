<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * @param array{
     *     total_xp: int,
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
                'totalXp' => $this->stats['total_xp'],
                'totalLevels' => $this->stats['total_levels'],
                'completedLevels' => $this->stats['completed_levels'],
                'correctAnswersPercentage' => $this->stats['correct_answers_percentage'],
            ],
        ];
    }
}
