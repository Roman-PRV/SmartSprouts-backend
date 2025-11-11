<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $image_url
 *
 * @OA\Schema(
 * schema="Level",
 * type="object",
 * title="Level",
 * required={"id", "title", "image_url"},
 *
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="title", type="string", example="Level 1"),
 * @OA\Property(property="image_url", type="string", format="url", example="https://example.com/storage/levels/level1.png")
 * )
 */
class Level extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function setTableForPrefix(string $prefix): static
    {
        $this->setTable($prefix.'_levels');

        return $this;
    }

    public function getImageUrlAttribute(): string
    {
        $diskName = ConfigHelper::getString('games.default_icon_disk', 'public');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $raw = $this->attributes['image_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        if ($path !== '' && $disk->exists($path)) {
            return url($disk->url($path));
        }

        $cfgDefaultIcon = ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png');

        return url($disk->url($cfgDefaultIcon));
    }
}
