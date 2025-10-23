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
        /** @var \App\Helpers\ConfigHelper $cfg */
        $cfg = app(\App\Helpers\ConfigHelper::class);

        $cfgDisk = $cfg->getString('games.default_icon_disk', 'public');
        $diskName = is_string($cfgDisk) && $cfgDisk !== '' ? $cfgDisk : 'public';

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $raw = $this->attributes['icon_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        if ($path !== '' && $disk->exists($path)) {
            return url($disk->url($path));
        }

        $cfgDefaultIcon = $cfg->getString('games.default_icon', 'icons/default-icon.png');

        return url($disk->url($cfgDefaultIcon));
    }
}
