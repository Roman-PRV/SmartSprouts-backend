<?php

namespace App\Http\Requests\Games\TrueFalseImage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
