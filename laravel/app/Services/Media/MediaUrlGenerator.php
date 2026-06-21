<?php

namespace App\Services\Media;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Helpers\MediaHelper;
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

        return MediaHelper::toAbsolute(Storage::disk($diskName)->url($path));
    }
}
