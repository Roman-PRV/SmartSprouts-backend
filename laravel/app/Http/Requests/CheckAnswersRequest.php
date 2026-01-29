<?php

namespace App\Http\Requests;

use App\DTO\CheckAnswersDTO;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CheckAnswersRequest",
 *     type="object",
 *     title="Check Answers Request",
 *     description="Request payload for validating player's answers. Includes automatically injected properties from route and authentication.",
 *     required={"answers"},
 *
 *     @OA\Property(
 *         property="answers",
 *         type="array",
 *         description="List of player's answers for each statement",
 *
 *         @OA\Items(
 *             type="object",
 *             required={"statement_id", "answer"},
 *
 *             @OA\Property(property="statement_id", type="integer", example=10, description="ID of the statement being answered"),
 *             @OA\Property(property="answer", type="boolean", example=true, description="Player's answer")
 *         )
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         readOnly=true,
 *         description="ID of the authenticated user (injected after validation)"
 *     ),
 *     @OA\Property(
 *         property="level_id",
 *         type="integer",
 *         readOnly=true,
 *         description="ID of the level from route (injected after validation)"
 *     ),
 *     @OA\Property(
 *         property="game",
 *         ref="#/components/schemas/Game",
 *         readOnly=true,
 *         description="Game model instance (injected after validation)"
 *     ),
 *
 *     example={
 *         "answers": {
 *             {"statement_id": 10, "answer": true},
 *             {"statement_id": 11, "answer": false}
 *         }
 *     }
 * )
 *
 * @property-read int $user_id
 * @property-read int $level_id
 * @property-read Game $game
 *
 * @method array validated(null|string $key = null, mixed $default = null)
 */
class CheckAnswersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.*.statement_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
        ];
    }

    protected function passedValidation()
    {
        $game = $this->route('game');

        if (! $game instanceof Game) {
            $game = Game::findOrFail($game);
        }

        $this->merge([
            'user_id' => auth()->id(),
            'game' => $game,
            'level_id' => $this->route('levelId'),
        ]);
    }

    public function toDTO(): CheckAnswersDTO
    {
        return new CheckAnswersDTO(
            userId: $this->user_id,
            game: $this->game,
            levelId: $this->level_id,
            answers: $this->validated('answers'),
        );
    }
}
