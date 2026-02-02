<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResultDTO;
use App\Enums\TranslationStatusEnum;
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
    public function translate(string $text): TranslationResultDTO
    {
        $key = $this->generateCacheKey($text);

        $result = Cache::remember($key, $this->ttl, function () use ($text) {
            return $this->provider->translate($text);
        });

        if (! $result instanceof TranslationResultDTO) {
            // This case should ideally not happen if cache is clean and types are correct,
            // but we add it to satisfy PHPStan and handle potential corrupted cache.
            Cache::forget($key);

            return $this->provider->translate($text);
        }

        // Do not cache results with errors to allow failover logic to work.
        // Transient failures (network issues, rate limiting) should not be cached long-term.
        if ($this->hasErrors($result)) {
            Cache::forget($key);

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
        $hash = hash('sha256', $text);
        $providerName = $this->getName();

        return "{$this->prefix}:{$providerName}:{$hash}";
    }

    /**
     * Check if the translation result contains any errors.
     */
    private function hasErrors(TranslationResultDTO $result): bool
    {
        foreach ($result->translations as $item) {
            if ($item->status === TranslationStatusEnum::Error) {
                return true;
            }
        }

        return false;
    }
}
