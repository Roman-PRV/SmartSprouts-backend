<?php

namespace App\Games\TrueFalseImage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="TrueFalseImage.AnswerRequest",
 *     type="object",
 *     description="Request payload for validating player's answers in the True/False Image game",
 *     required={"image_id", "answers"},
 *
 *     @OA\Property(property="image_id", type="integer", example=1, description="ID of the image level being answered"),
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
     * Determine if the user is authorized to make this request.
     */
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
            'image_id' => 'required|exists:true_false_image_levels,id',
            'answers' => 'required|array',
            'answers.*.statement_id' => 'required|exists:true_false_image_statements,id',
            'answers.*.answer' => 'required|boolean',
        ];
    }
}
