<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use App\Helpers\MediaHelper;
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
 *
 * @OA\Schema(
 *   schema="ValidationErrorResponse",
 *   type="object",
 *   description="Returned on 422 when request validation fails.",
 *
 *   @OA\Property(property="message", type="string", example="The given data was invalid."),
 *   @OA\Property(
 *     property="errors",
 *     type="object",
 *     description="Map of field name to the list of its validation messages.",
 *     example={"duration_seconds": {"The duration seconds field is required."}},
 *
 *     @OA\AdditionalProperties(
 *       type="array",
 *
 *       @OA\Items(type="string")
 *     )
 *   )
 * )
 */
class Level extends Model
{
    use HasFactory;

    /**
     * Resolve the public URL for this level's cover image.
     *
     * Trusts the stored path: when image_url is set the upload-disk URL is
     * built directly, with no exists() probe. On a cloud disk (R2/S3) a probe
     * would cost a HeadObject request per access — an N+1 over the network on
     * list endpoints. A missing file therefore surfaces as a broken URL (404)
     * instead of being silently masked; keeping stored paths valid is the data
     * layer's responsibility. Only an empty path falls back to the configured
     * default icon on the static disk.
     */
    public function getImageUrlAttribute(): string
    {
        $raw = $this->attributes['image_url'] ?? null;
        $path = is_string($raw) ? ltrim($raw, '/') : '';

        if ($path !== '') {
            $uploadDiskName = ConfigHelper::getString('games.upload_disk', 'public');
            /** @var FilesystemAdapter $uploadDisk */
            $uploadDisk = Storage::disk($uploadDiskName);

            return MediaHelper::toAbsolute($uploadDisk->url($path));
        }

        $staticDiskName = ConfigHelper::getString('games.default_icon_disk', 'static');
        /** @var FilesystemAdapter $staticDisk */
        $staticDisk = Storage::disk($staticDiskName);
        $defaultKey = ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png');

        return MediaHelper::toAbsolute($staticDisk->url($defaultKey));
    }
}
