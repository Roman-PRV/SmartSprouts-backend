<?php

namespace App\Games\Arithmetic\Models;

use App\Helpers\ConfigHelper;
use App\Models\Level;
use App\Services\Media\MediaUrl;

/**
 * In-memory level for the arithmetic game family. Content is fully
 * deterministic, so there is no table and no migration: the service builds
 * instances with `new` and never persists them ($exists stays false). It
 * extends Level only to inherit the image_url accessor (an empty path falls
 * back to the default icon) and the common id/title shape.
 *
 * @property string $operator Operation symbol for the level (e.g. "×", "+").
 * @property array<int, array<string, int|string>> $equations Facts for the level; empty on list, populated on show.
 */
class ArithmeticLevel extends Level
{
    /**
     * Resolve the level icon against the static (git-committed) disk. Unlike the
     * base Level — whose images are admin-uploaded to the cloud upload disk —
     * arithmetic icons are curated assets shipped with the app, so both the
     * per-level path and the empty fallback resolve on the static disk.
     */
    public function getImageUrlAttribute(): string
    {
        $path = MediaUrl::normalizePath($this->attributes['image_url'] ?? null);

        if ($path === '') {
            $path = ConfigHelper::getString('games.default_level_image', 'icons/default-icon.png');
        }

        return MediaUrl::diskUrl(ConfigHelper::getString('games.default_icon_disk', 'static'), $path);
    }
}
