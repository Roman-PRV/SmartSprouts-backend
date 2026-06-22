<?php

namespace App\Games\Arithmetic\Http\Requests;

use App\DTO\CheckAnswersDTO;
use App\Games\Arithmetic\Support\ArithmeticConstants;
use App\Models\Game;
use App\Traits\RespondsWithJsonValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a player's submission for an arithmetic level: one answer per
 * equation in the level. The set of equation_ids must cover 1..FACTS_PER_LEVEL
 * exactly once — partial or duplicated submissions are rejected so the derived
 * score is always over the full level. The answer value itself is just an
 * integer; correctness is decided server-side in the service.
 *
 * @OA\Schema(
 *     schema="Arithmetic.AttemptRequest",
 *     type="object",
 *     title="Arithmetic Attempt Request",
 *     description="Player's answers for every equation in an arithmetic level.",
 *     required={"answers"},
 *
 *     @OA\Property(
 *         property="answers",
 *         type="array",
 *         description="One entry per equation; together they must cover the whole level.",
 *
 *         @OA\Items(
 *             type="object",
 *             required={"equation_id", "answer"},
 *
 *             @OA\Property(property="equation_id", type="integer", minimum=1, maximum=10, example=4),
 *             @OA\Property(property="answer", type="integer", example=12)
 *         )
 *     )
 * )
 *
 * @property-read int $user_id
 * @property-read int $level_id
 * @property-read Game $game
 */
class ArithmeticAttemptRequest extends FormRequest
{
    use RespondsWithJsonValidation;

    /**
     * Rights are enforced by sanctum + GameMatches middleware; level existence
     * is handled by the service. Nothing extra to authorize here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.*.equation_id' => 'required|integer|distinct|between:1,'.ArithmeticConstants::FACTS_PER_LEVEL,
            'answers.*.answer' => 'required|integer',
        ];
    }

    /**
     * The equation_ids must cover 1..FACTS_PER_LEVEL exactly (no gaps). Built-in
     * rules can express uniqueness and bounds but not full coverage.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $equationIds = array_map(
                static fn (array $answer): int => (int) $answer['equation_id'],
                $this->input('answers', []),
            );

            sort($equationIds);

            // After sorting, an exact, gap-free, duplicate-free cover of the
            // level equals [1, 2, …, FACTS_PER_LEVEL]. This holds because
            // equation_id == operand_b by invariant (see ArithmeticGameService::factsFor).
            if ($equationIds !== range(1, ArithmeticConstants::FACTS_PER_LEVEL)) {
                $v->errors()->add('answers', __('validation.arithmetic.answers_coverage'));
            }
        });
    }

    protected function passedValidation(): void
    {
        $game = $this->route('game');

        if (! $game instanceof Game) {
            $game = Game::findOrFail($game);
        }

        $this->merge([
            'user_id' => auth()->id(),
            'game' => $game,
            'level_id' => (int) $this->route('level'),
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
