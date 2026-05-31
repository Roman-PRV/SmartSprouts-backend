<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $title_audio_url
 * @property string|null $image_url
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
        $raw = $this->attributes['image_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        if ($path !== '') {
            $uploadDiskName = ConfigHelper::getString('games.upload_disk', 'public');
            /** @var FilesystemAdapter $uploadDisk */
            $uploadDisk = Storage::disk($uploadDiskName);

            if ($uploadDisk->exists($path)) {
                return $this->absoluteUrl($uploadDisk->url($path));
            }
        }

        $staticDiskName = ConfigHelper::getString('games.default_icon_disk', 'static');
        /** @var FilesystemAdapter $staticDisk */
        $staticDisk = Storage::disk($staticDiskName);

        $key = $path !== '' && $staticDisk->exists($path)
            ? $path
            : ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png');

        return $this->absoluteUrl($staticDisk->url($key));
    }

    private function absoluteUrl(string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
