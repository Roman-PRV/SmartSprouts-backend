<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class MediaHelper
{
    /**
     * Get absolute URL for a path using a disk from configuration.
     *
     * @param  string|null  $path  Relative storage path
     * @param  string  $diskConfigKey  Config key for the disk name
     * @param  string  $defaultDisk  Default disk if config is missing
     */
    public static function getUrl(?string $path, string $diskConfigKey = 'ai.tts.storage.disk', string $defaultDisk = 'public'): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = ConfigHelper::getString($diskConfigKey, $defaultDisk);

        return self::toAbsolute(Storage::disk($diskName)->url($path));
    }

    /**
     * Promote a disk-generated URL to an absolute one.
     *
     * Cloud disks (s3/R2) already return absolute URLs; local disks return a
     * site-relative path, which we prefix with the app URL. Single source of
     * truth reused by the media services and the model accessors (Game, Level).
     */
    public static function toAbsolute(string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    /**
     * Get absolute URL for an attribute (potentially translatable) from a model.
     *
     * @param  object  $model  Model instance
     * @param  string  $attribute  Attribute name
     * @param  string  $diskConfigKey  Config key for the disk name
     * @param  string  $defaultDisk  Default disk if config is missing
     * @param  string|null  $locale  Optional locale for translation
     */
    public static function getAttributeUrl(
        object $model,
        string $attribute,
        string $diskConfigKey = 'ai.tts.storage.disk',
        string $defaultDisk = 'public',
        ?string $locale = null
    ): ?string {
        $locale = $locale ?: app()->getLocale();

        $path = null;
        if (method_exists($model, 'getTranslation')) {
            /** @var string|null $path */
            $path = $model->getTranslation($attribute, $locale, false);
        } else {
            $path = $model->{$attribute} ?? null;
        }

        return self::getUrl($path ?: null, $diskConfigKey, $defaultDisk);
    }
}
