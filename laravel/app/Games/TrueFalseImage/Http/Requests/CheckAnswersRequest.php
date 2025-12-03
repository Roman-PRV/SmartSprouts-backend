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
                    $ids = [];
                    foreach ($value as $item) {
                        if (isset($item['statement_id'])) {
                            $ids[] = (int) $item['statement_id'];
                        }
                    }

                    if (empty($ids)) {

                        return;
                    }

                    $levelId = (int) $this->route('levelId');

                    /**
                     * @var \Illuminate\Support\Collection<int, TrueFalseImageStatement> $statements
                     */
                    $statements = TrueFalseImageStatement::whereIn('id', $ids)->get();

                    foreach ($ids as $id) {
                        $statement = $statements->firstWhere('id', $id);
                        if ($statement === null) {
                            $fail("The statement {$id} does not exist.");

                            continue;
                        }

                        $levelValue = $statement->level_id ?? null;

                        if ((int) $levelValue !== $levelId) {
                            $fail("The statement {$id} does not belong to level {$levelId} {$statement}.");
                        }
                    }
                },
            ],
            'answers.*.statement_id' => 'required|exists:true_false_image_statements,id',
            'answers.*.answer' => 'required|boolean',
        ];
    }
}
