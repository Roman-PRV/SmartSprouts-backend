<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
use Illuminate\Support\Facades\Cache;

class CachingTranslationProvider implements TranslationProviderInterface
{
    /**
     * @param  TranslationProviderInterface  $provider  The decorated provider
     * @param  int  $ttl  Cache time-to-live in seconds
     * @param  string  $prefix  Cache key prefix
     */
    public function __construct(
        private readonly TranslationProviderInterface $provider,
        private readonly int $ttl,
        private readonly string $prefix = 'translation'
    ) {}

    /**
     * {@inheritDoc}
     */
    public function translate(string $text): TranslationResult
    {
        $key = $this->generateCacheKey($text);

        $result = Cache::remember($key, $this->ttl, function () use ($text) {
            return $this->provider->translate($text);
        });

        if (! $result instanceof TranslationResult) {
            // This case should ideally not happen if cache is clean and types are correct,
            // but we add it to satisfy PHPStan and handle potential corrupted cache.
            return $this->provider->translate($text);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->provider->getName();
    }

    /**
     * Generate a unique cache key for the given text and provider.
     */
    private function generateCacheKey(string $text): string
    {
        $hash = md5($text);
        $providerName = $this->getName();

        return "{$this->prefix}:{$providerName}:{$hash}";
    }
}
