<?php

namespace App\Http\Requests;

use App\Traits\RespondsWithJsonValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @property string $name
 * @property string $email
 * @property string $password
 */
class RegisterRequest extends FormRequest
{
    use RespondsWithJsonValidation;

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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
