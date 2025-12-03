<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CheckAnswersRequest",
 *     type="object",
 *     description="Generic request payload for validating player's answers across all game types",
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
            'answers' => 'required|array',
            'answers.*.statement_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
        ];
    }
}
