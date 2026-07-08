<?php

namespace App\Services\Tts;

/**
 * Single source of truth for the TTS path-hash convention.
 *
 * Every generated audio file is stored as `…/{attr}_{hash}.{ext}` where the
 * hash is derived from the exact (text, locale) pair the audio was synthesized
 * from. Comparing the hash baked into a stored path against the hash of the
 * model's current text is how staleness is detected: when the source text
 * changes, its hash changes, so the path no longer matches.
 *
 * The md5/length convention lives here and nowhere else — both the generator
 * (when building the path) and the staleness check (when reading it back) must
 * agree, so duplicating the formula would invite silent drift.
 */
final class TtsPathHash
{
    /**
     * Length of the hex hash segment embedded in audio file names.
     */
    public const LENGTH = 8;

    /**
     * Build the hash for a (text, locale) pair — the value baked into the
     * audio file name at generation time.
     */
    public static function forText(string $text, string $locale): string
    {
        return substr(md5($text.$locale), 0, self::LENGTH);
    }

    /**
     * Read the hash back out of a stored audio path, or null when the path is
     * empty or does not carry a recognizable `…_{hash}.{ext}` suffix.
     */
    public static function extractFromPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/_([0-9a-f]{'.self::LENGTH.'})\.[^.\/]+$/', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
