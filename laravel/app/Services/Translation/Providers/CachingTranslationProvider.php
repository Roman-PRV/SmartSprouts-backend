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

        $cached = Cache::get($key);

        if ($cached instanceof TranslationResultDTO && ! $this->hasErrors($cached)) {
            return $cached;
        }

        $result = $this->provider->translate($text);

        if ($result instanceof TranslationResultDTO && ! $this->hasErrors($result)) {
            Cache::put($key, $result, $this->ttl);
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
