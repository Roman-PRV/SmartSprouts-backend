<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DestroyProfileRequest extends FormRequest
{
    /**
     * Any authenticated user may delete their own account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Password accounts re-confirm their password; password-less (Google-only)
     * accounts confirm with the one-time emailed code instead.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        if ($user->hasPassword()) {
            return [
                'password' => ['required', 'string', 'current_password:sanctum'],
            ];
        }

        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
