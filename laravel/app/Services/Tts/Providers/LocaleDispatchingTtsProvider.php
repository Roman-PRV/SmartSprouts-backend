<?php

namespace App\Services\Tts\Providers;

use App\Contracts\TtsProviderInterface;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches TTS synthesis to different providers based on the request locale.
 *
 * Useful in local development where multiple self-hosted providers handle
 * different languages (e.g. Kokoro for English/Spanish, UkrainianTts for Ukrainian).
 */
class LocaleDispatchingTtsProvider implements TtsProviderInterface
{
    /**
     * @param  array<string, TtsProviderInterface>  $localeMap  locale → provider
     * @param  TtsProviderInterface  $fallback  used when locale is absent or unmapped
     */
    public function __construct(
        private readonly array $localeMap,
        private readonly TtsProviderInterface $fallback,
    ) {}

    public function synthesize(TtsRequestDTO $request): TtsResultDTO
    {
        $provider = $this->resolveProvider($request->locale);

        return $provider->synthesize($request);
    }

    public function getName(): string
    {
        return 'locale_dispatch';
    }

    /**
     * Returns the merged list of voices from all registered providers.
     *
     * @return array<string, array{name: string, language: string, gender?: string}>
     */
    public function getAvailableVoices(): array
    {
        $voices = [];

        foreach ($this->uniqueProviders() as $provider) {
            $voices = array_merge($voices, $provider->getAvailableVoices());
        }

        return $voices;
    }

    private function resolveProvider(?string $locale): TtsProviderInterface
    {
        if ($locale !== null && isset($this->localeMap[$locale])) {
            return $this->localeMap[$locale];
        }

        if ($locale !== null) {
            Log::warning('LocaleDispatchingTtsProvider: no provider mapped for locale, using fallback.', [
                'locale' => $locale,
                'fallback' => $this->fallback->getName(),
            ]);
        }

        return $this->fallback;
    }

    /**
     * Deduplicated list of providers (map values + fallback).
     *
     * @return TtsProviderInterface[]
     */
    private function uniqueProviders(): array
    {
        $all = array_values($this->localeMap);
        $all[] = $this->fallback;

        $seen = [];
        $unique = [];

        foreach ($all as $provider) {
            $name = $provider->getName();
            if (! isset($seen[$name])) {
                $seen[$name] = true;
                $unique[] = $provider;
            }
        }

        return $unique;
    }
}
