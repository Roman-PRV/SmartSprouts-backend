<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $title_audio_url
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
 * @OA\Property(property="image_url", type="string", format="uri", example="https://example.com/storage/levels/level1.png")
 * )
 *
 * @OA\Schema(
 *   schema="LevelCollection",
 *   type="array",
 *
 *   @OA\Items(ref="#/components/schemas/Level")
 * )
 *
 * @OA\Schema(
 *   schema="ErrorResponse",
 *   type="object",
 *
 *   @OA\Property(property="message", type="string", example="Not found")
 * )
 */
class Level extends Model
{
    use HasFactory;

    public function getImageUrlAttribute(): string
    {
        $diskName = ConfigHelper::getString('games.default_icon_disk', 'static');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $raw = $this->attributes['image_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        $key = $path !== '' && $disk->exists($path)
            ? $path
            : ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png');

        $url = $disk->url($key);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
