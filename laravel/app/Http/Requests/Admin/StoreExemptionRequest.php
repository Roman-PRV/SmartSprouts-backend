<?php

namespace App\Http\Requests\Admin;

use App\Enums\Entitlement\ExemptionReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * @OA\Schema(
 *     schema="Admin.StoreExemptionRequest",
 *     type="object",
 *     title="Admin Store Exemption Request",
 *     required={"user_id", "reason"},
 *
 *     @OA\Property(property="user_id", type="integer", example=42),
 *     @OA\Property(property="reason", type="string", enum={"staff", "tester"}, example="tester"),
 *     @OA\Property(property="note", type="string", nullable=true, maxLength=500, description="Omitted on a re-grant, this clears the existing note.", example="Beta group")
 * )
 */
class StoreExemptionRequest extends FormRequest
{
    /**
     * Delegated to the route middleware (auth + EnsureAdmin).
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
            'user_id' => 'required|integer|exists:users,id',
            'reason' => ['required', new Enum(ExemptionReasonEnum::class)],
            'note' => 'nullable|string|max:500',
        ];
    }
}
