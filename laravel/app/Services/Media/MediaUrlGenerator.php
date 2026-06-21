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

        return MediaUrl::toAbsolute(Storage::disk($diskName)->url($path));
    }
}
