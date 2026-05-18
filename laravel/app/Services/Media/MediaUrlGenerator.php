<?php

namespace App\Services\Media;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use Illuminate\Support\Facades\Storage;

class MediaUrlGenerator implements MediaUrlGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function getUrl(?string $path, string $diskName = 'public'): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk($diskName)->url($path);

        // Cloud disks (s3/R2) return absolute URLs already; only prefix relative paths.
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
