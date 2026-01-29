<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 * schema="Game",
 * type="object",
 * title="Game",
 * required={"id", "key", "icon_url", "is_active", "title", "description"},
 *
 * @OA\Property(property="id", type="string", example="1"),
 * @OA\Property(property="key", type="string", example="find_the_wrong"),
 * @OA\Property(property="title", type="string", description="Full title of the game.", example="Find The Wrong"),
 * @OA\Property(property="description", type="string", description="Short description of the game.", example="Find the incorrect statement among the options."),
 * @OA\Property(property="icon_url", type="string", format="url", example="https://example.com/storage/icons/game1.png"),
 * @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class Game extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getIconUrlAttribute(): string
    {
        $diskName = ConfigHelper::getString('games.default_icon_disk', 'public');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $raw = $this->attributes['icon_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        if ($path !== '' && $disk->exists($path)) {
            return url($disk->url($path));
        }

        $cfgDefaultIcon = ConfigHelper::getString('games.default_icon', 'icons/default-icon.png');

        return url($disk->url($cfgDefaultIcon));
    }

    public function gameResults(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }
}
