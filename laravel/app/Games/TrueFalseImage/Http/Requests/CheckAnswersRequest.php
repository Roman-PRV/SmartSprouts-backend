<?php

namespace App\Games\TrueFalseImage\Http\Requests;

use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="TrueFalseImage.AnswerRequest",
 *     type="object",
 *     description="Request payload for validating player's answers in the True/False Image game",
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
 *             @OA\Property(property="answer", type="boolean", example=true, description="Player's answer: true or false")
 *         )
 *     )
 * )
 */
class CheckAnswersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $levelId = (int) $this->route('levelId');

                    foreach ($value as $index => $answer) {
                        if (! isset($answer['statement_id'])) {
                            continue;
                        }

                        /** @var TrueFalseImageStatement|null $statement */
                        $statement = TrueFalseImageStatement::find($answer['statement_id']);

                        if ($statement && $statement->level_id !== $levelId) {
                            $fail("The statement {$answer['statement_id']} does not belong to level {$levelId}.");
                        }
                    }
                },
            ],
            'answers.*.statement_id' => 'required|exists:true_false_image_statements,id',
            'answers.*.answer' => 'required|boolean',
        ];
    }
}
