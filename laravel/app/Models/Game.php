<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="Game",
 *     type="object",
 *     title="Game",
 *     required={"id", "key", "icon_url", "is_active"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="key", type="string", example="find_the_wrong"),
 *     @OA\Property(property="icon_url", type="string", format="url", example="https://example.com/storage/icons/game1.png"),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class Game extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getIconUrlAttribute(): string
    {
        $raw = $this->attributes['icon_url'] ?? '';

        if (is_string($raw) && $raw !== '') {
            return url(Storage::url($raw));
        }

        return url(Storage::url('icons/default-icon.png'));
    }
}
