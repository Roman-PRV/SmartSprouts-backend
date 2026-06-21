<?php

namespace App\Services\Media;

/**
 * Dependency-free leaf with the pure, low-level media-URL primitives shared by
 * the media services (MediaUrlGenerator) and the model accessors (Game, Level).
 *
 * Keeping these here — rather than on MediaHelper — avoids an inverted
 * dependency: MediaUrlGenerator would otherwise depend on the ergonomic
 * MediaHelper facade that sits above it.
 */
final class MediaUrl
{
    /**
     * Promote a disk-generated URL to an absolute one.
     *
     * Cloud disks (s3/R2) already return absolute URLs; local disks return a
     * site-relative path, which we prefix with the app URL.
     *
     * Assumes a disk URL is either an absolute http(s) URL or a scheme-less
     * site-relative path. Protocol-relative ("//cdn/…") or other schemes are
     * not produced by the current disks; revisit this check if a CDN that
     * emits them is added.
     */
    public static function toAbsolute(string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    /**
     * Normalize a raw stored path: trim a leading slash so it resolves cleanly
     * against a disk, and coerce any non-string value to an empty path.
     */
    public static function normalizePath(mixed $raw): string
    {
        return is_string($raw) ? ltrim($raw, '/') : '';
    }
}
