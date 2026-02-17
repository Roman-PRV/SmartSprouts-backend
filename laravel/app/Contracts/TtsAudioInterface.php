<?php

namespace App\Contracts;

/**
 * Interface TtsAudioInterface
 *
 * Contract for models that support Text-to-Speech audio generation.
 */
interface TtsAudioInterface
{
    /**
     * Get the source text attribute for a given audio attribute.
     *
     * @param  string  $audioAttribute  Example: 'statement_audio_url'
     * @return string Example: 'statement'
     */
    public function getTtsSourceAttribute(string $audioAttribute): string;

    /**
     * Get the text content for TTS synthesis from an audio attribute and locale.
     *
     * @param  string  $audioAttribute  The attribute name (e.g., 'statement_audio_url')
     * @param  string  $locale  The locale for which to get the text
     * @return string|null The text content or null if not found
     */
    public function getTtsText(string $audioAttribute, string $locale): ?string;

    /**
     * Get the translatable attribute for a given attribute and locale.
     *
     * @param  string  $attribute  The attribute name
     * @param  string  $locale  The locale
     * @return string|null The attribute value or null if not found
     */
    public function getTranslatableAttribute(string $attribute, string $locale): ?string;

    /**
     * Set the audio path for a given attribute and locale.
     *
     * @param  string  $attribute  The attribute name (e.g., 'statement_audio_url')
     * @param  string  $locale  The locale for which to set the path
     * @param  string  $path  The path to the audio file
     */
    public function setAudioPath(string $attribute, string $locale, string $path): void;
}
