<?php

namespace App\Services\Tts;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Contracts\TtsAudioInterface;
use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only audio status for a model's TTS field: the playable URL plus a
 * staleness flag, per locale. No generation, no DB writes — admin resources
 * call this to decide whether the "regenerate" button should light up.
 *
 * Staleness mirrors exactly the generator's path convention (TtsPathHash):
 * the hash baked into the stored path is compared against the hash of the
 * model's current source text. An empty path with non-empty text is stale by
 * definition (audio simply missing); empty text is never stale (nothing to
 * speak).
 */
class TtsAudioStatusService
{
    public function __construct(private readonly MediaUrlGeneratorInterface $urlGenerator) {}

    /**
     * Status for a single (audio field, locale).
     *
     * @return array{url: string|null, is_stale: bool}
     */
    public function statusFor(TtsAudioInterface&Model $model, string $audioAttribute, string $locale): array
    {
        $sourceAttribute = $model->getTtsSourceAttribute($audioAttribute);
        $text = $model->getTranslatableAttribute($sourceAttribute, $locale);
        $path = $model->getTranslatableAttribute($audioAttribute, $locale);

        return [
            'url' => $this->urlGenerator->getUrl($path, $this->diskName()),
            'is_stale' => $this->isStale($text, $path, $locale),
        ];
    }

    /**
     * Per-locale status map for one audio field, over the supported locales.
     *
     * @return array<string, array{url: string|null, is_stale: bool}>
     */
    public function mapFor(TtsAudioInterface&Model $model, string $audioAttribute): array
    {
        $map = [];

        foreach (ConfigHelper::getStringList('app.supported_locales', ['en']) as $locale) {
            $map[$locale] = $this->statusFor($model, $audioAttribute, $locale);
        }

        return $map;
    }

    /**
     * Stale when the source text exists but its hash no longer matches the
     * hash embedded in the stored path (changed text, or no audio yet).
     */
    private function isStale(?string $text, ?string $path, string $locale): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        return TtsPathHash::extractFromPath($path) !== TtsPathHash::forText($text, $locale);
    }

    private function diskName(): string
    {
        return ConfigHelper::getString('games.upload_disk', 'public');
    }
}
