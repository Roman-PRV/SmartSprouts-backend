<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasTtsAudio
 *
 * Provides utility methods to map audio attributes to their source text attributes
 * for Text-to-Speech generation.
 *
 * @mixin Model
 */
trait HasTtsAudio
{
    /**
     * Get the source text attribute for a given audio attribute.
     * By default, it removes the '_audio_url' suffix.
     *
     * @param  string  $audioAttribute  Example: 'statement_audio_url'
     * @return string Example: 'statement'
     */
    public function getTtsSourceAttribute(string $audioAttribute): string
    {
        return str_replace('_audio_url', '', $audioAttribute);
    }

    /**
     * Get the text content for TTS synthesis from an audio attribute and locale.
     *
     * @param  string  $audioAttribute  The attribute name (e.g., 'statement_audio_url')
     * @param  string  $locale  The locale for which to get the text
     * @return string|null The text content or null if not found
     */
    public function getTtsText(string $audioAttribute, string $locale): ?string
    {
        $sourceAttribute = $this->getTtsSourceAttribute($audioAttribute);

        return $this->getTranslatableAttribute($sourceAttribute, $locale);
    }

    public function getTranslatableAttribute(string $attribute, string $locale): ?string
    {
        if (method_exists($this, 'getTranslation')) {
            /** @var string|null */
            return $this->getTranslation($attribute, $locale, false);
        }

        return $this->{$attribute} ?? null;
    }

    public function setAudioPath(string $attribute, string $locale, string $path): void
    {
        if (method_exists($this, 'setTranslation')) {
            $this->setTranslation($attribute, $locale, $path);
        } else {
            $this->{$attribute} = $path;
        }

        $this->save();
    }
}
