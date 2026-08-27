<?php

namespace App\Http\Resources\Admin;

use App\Models\Entitlement\AccessExemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessExemption
 *
 * @OA\Schema(
 *     schema="Admin.Exemption",
 *     type="object",
 *     title="Admin Access Exemption",
 *
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=42),
 *         @OA\Property(property="email", type="string", format="email", example="t@example.com"),
 *         @OA\Property(property="name", type="string", example="Test Parent")
 *     ),
 *     @OA\Property(property="reason", type="string", enum={"staff", "tester"}, example="tester"),
 *     @OA\Property(property="note", type="string", nullable=true, example="Beta group, invited 2026-08-20"),
 *     @OA\Property(
 *         property="granted_by",
 *         type="object",
 *         nullable=true,
 *         description="Null once the granting admin's own account is deleted; the grant itself survives.",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Admin")
 *     ),
 *     @OA\Property(property="granted_at", type="string", format="date-time")
 * )
 */
class ExemptionAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AccessExemption $exemption */
        $exemption = $this->resource;

        $grantedBy = $exemption->grantedBy;

        return [
            'user' => [
                'id' => $exemption->user->id,
                'email' => $exemption->user->email,
                'name' => $exemption->user->name,
            ],
            'reason' => $exemption->reason->value,
            'note' => $exemption->note,
            'granted_by' => $grantedBy === null ? null : [
                'id' => $grantedBy->id,
                'name' => $grantedBy->name,
            ],
            'granted_at' => $exemption->granted_at,
        ];
    }
}
