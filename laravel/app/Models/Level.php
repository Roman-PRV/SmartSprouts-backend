<?php

namespace App\Models;

use App\Helpers\ConfigHelper;
use App\Services\Media\MediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $title_audio_url
 * @property string|null $image_url
 * @property string|null $progress Virtual: player's per-level progress, set by LevelProgressService on list endpoints
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
 *
 * @OA\Schema(
 *   schema="DailyLimitReachedResponse",
 *   type="object",
 *   description="403 on either gate once that gate's own daily counter is spent.",
 *
 *   @OA\Property(property="message", type="string", description="Follows limit_kind: the two gates carry their own text.", example="You have already opened every level available today"),
 *   @OA\Property(property="error_type", type="string", example="LEVEL_LIMIT_REACHED"),
 *   @OA\Property(
 *     property="details",
 *     type="object",
 *     description="limit_kind names the counter this gate enforces: started on opening, completed on submitting.",
 *     @OA\Property(property="limit_kind", type="string", enum={"started", "completed"}, example="started"),
 *     @OA\Property(property="resets_at", type="string", format="date-time", example="2026-08-24T00:00:00Z"),
 *     @OA\Property(property="purchasing_enabled", type="boolean", example=true)
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="LevelNotOpenedTodayResponse",
 *   type="object",
 *   description="403 on submitting a level with no recorded open for today — either it was never opened, or the open expired at midnight. Not a limit: the client re-fetches the level and resubmits. A 404 on that re-fetch means the level is gone and the attempt cannot be recorded.",
 *
 *   @OA\Property(property="message", type="string", example="Open this level before submitting"),
 *   @OA\Property(property="error_type", type="string", example="LEVEL_NOT_OPENED_TODAY")
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
        $path = MediaUrl::normalizePath($this->attributes['image_url'] ?? null);

        if ($path !== '') {
            return MediaUrl::diskUrl(ConfigHelper::uploadDisk(), $path);
        }

        return MediaUrl::diskUrl(
            ConfigHelper::getString('games.default_icon_disk', 'static'),
            ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png'),
        );
    }
}
