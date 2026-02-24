<?php

namespace App\Contracts\Media;

interface MediaUrlGeneratorInterface
{
    /**
     * Get absolute URL for a storage path.
     *
     * @param  string|null  $path  Relative storage path
     * @param  string  $diskName  Optional storage disk name, defaults to 'public'
     * @return string|null The absolute URL or null
     */
    public function getUrl(?string $path, string $diskName = 'public'): ?string;
}
