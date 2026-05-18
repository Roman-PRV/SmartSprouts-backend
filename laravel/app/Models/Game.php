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

    public function getIconUrlAttribute(): string
    {
        $diskName = ConfigHelper::getString('games.default_icon_disk', 'static');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $raw = $this->attributes['icon_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        $key = $path !== '' && $disk->exists($path)
            ? $path
            : ConfigHelper::getString('games.default_icon', 'icons/default-icon.png');

        $url = $disk->url($key);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    public function gameResults(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }
}
