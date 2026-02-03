<?php

namespace Tests\Feature\Cache;

use App\DTO\TranslationItemDTO;
use App\DTO\TranslationResultDTO;
use App\Enums\TranslationStatusEnum;
use App\Services\Translation\Providers\CachingTranslationProvider;
use App\Services\Translation\Providers\DeepLProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class RedisTranslationCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'redis');

        try {
            Redis::connection('cache')->flushdb();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not available: '.$e->getMessage());
        }
    }

    public function test_it_caches_translation_results_in_redis(): void
    {
        // Arrange
        $mockProvider = Mockery::mock(DeepLProvider::class);
        $mockProvider->shouldReceive('getName')->andReturn('deepl');

        $resultDTO = new TranslationResultDTO(
            translations: [
                'es' => new TranslationItemDTO(
                    status: TranslationStatusEnum::Success,
                    text: 'Hola'
                ),
            ],
            requestId: 'test-request-id'
        );

        $mockProvider->shouldReceive('translate')
            ->once()
            ->with('Hello')
            ->andReturn($resultDTO);

        $cachingProvider = new CachingTranslationProvider($mockProvider, 3600, 'test_pref');

        // Act
        $firstResult = $cachingProvider->translate('Hello');
        $secondResult = $cachingProvider->translate('Hello');

        // Assert
        $this->assertSame($resultDTO, $firstResult);
        // Result from Redis is a new instance due to serialization
        $this->assertEquals($resultDTO, $secondResult);

        $store = Cache::getStore();
        $prefix = $store->getPrefix();
        $hash = hash('sha256', 'Hello');
        $fullKey = "{$prefix}test_pref:deepl:{$hash}";

        $ttl = Redis::connection('cache')->ttl($fullKey);

        $this->assertGreaterThan(3590, $ttl, "TTL for key {$fullKey} is too low: {$ttl}");
        $this->assertLessThanOrEqual(3600, $ttl, "TTL for key {$fullKey} is too high: {$ttl}");
    }

    public function test_it_does_not_cache_error_results(): void
    {
        // Arrange
        $mockProvider = Mockery::mock(DeepLProvider::class);
        $mockProvider->shouldReceive('getName')->andReturn('deepl');

        $errorResultDTO = new TranslationResultDTO(
            translations: [
                'es' => new TranslationItemDTO(status: TranslationStatusEnum::Error),
            ],
            requestId: 'error-request-id'
        );

        // Expect translate to be called twice because it should not be cached
        $mockProvider->shouldReceive('translate')
            ->twice()
            ->with('Error Text')
            ->andReturn($errorResultDTO);

        $cachingProvider = new CachingTranslationProvider($mockProvider, 3600, 'test_pref');

        // Act
        $firstResult = $cachingProvider->translate('Error Text');
        $secondResult = $cachingProvider->translate('Error Text');

        // Assert
        // Since results are not cached, both calls return the same object from the mock
        $this->assertSame($errorResultDTO, $firstResult);
        $this->assertSame($errorResultDTO, $secondResult);

        $keys = Redis::connection('cache')->keys('*test_pref:deepl*');
        $this->assertEmpty($keys);
    }
}
