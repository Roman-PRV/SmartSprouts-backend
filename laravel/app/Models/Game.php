<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use App\Services\Media\MediaUrl;
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
 * @OA\Property(property="icon_url", type="string", format="uri", example="https://example.com/storage/icons/game1.png"),
 * @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'table_prefix',
        'icon_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Resolve the public URL for this game's icon.
     *
     * Mirrors Level::getImageUrlAttribute(): the stored path is trusted and the
     * URL is built without an exists() probe. A missing file surfaces as a
     * broken URL (404) instead of being masked; only an empty path falls back
     * to the configured default icon. Icons live on the static (local,
     * git-committed) disk, so there is no cloud round-trip either way — the
     * point here is behavioural consistency with the Level accessor.
     */
    public function getIconUrlAttribute(): string
    {
        $path = MediaUrl::normalizePath($this->attributes['icon_url'] ?? null);

        $key = $path !== ''
            ? $path
            : ConfigHelper::getString('games.default_icon', 'icons/default-icon.png');

        return MediaUrl::diskUrl(ConfigHelper::getString('games.default_icon_disk', 'static'), $key);
    }

    public function gameResults(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }
}
