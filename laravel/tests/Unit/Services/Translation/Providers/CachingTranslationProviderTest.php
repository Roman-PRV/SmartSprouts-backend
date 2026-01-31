<?php

namespace Tests\Unit\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
use App\Services\Translation\Providers\CachingTranslationProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachingTranslationProviderTest extends TestCase
{
    private TranslationProviderInterface $mockProvider;

    private CachingTranslationProvider $cachingProvider;

    private int $ttl = 3600;

    private string $prefix = 'test_translation';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProvider = $this->createMock(TranslationProviderInterface::class);
        $this->cachingProvider = new CachingTranslationProvider(
            $this->mockProvider,
            $this->ttl,
            $this->prefix
        );

        Cache::flush();
    }

    public function test_it_caches_translation_results(): void
    {
        $text = 'Hello world';
        $result = new TranslationResult(['en' => 'Hello world', 'uk' => 'Привіт світ']);

        $this->mockProvider->expects($this->once())
            ->method('translate')
            ->with($text)
            ->willReturn($result);

        $this->mockProvider->method('getName')->willReturn('mock');

        // First call: should call the real provider
        $firstResult = $this->cachingProvider->translate($text);
        $this->assertEquals($result->translations, $firstResult->translations);

        // Second call: should NOT call the real provider (expects once was set)
        $secondResult = $this->cachingProvider->translate($text);
        $this->assertEquals($result->translations, $secondResult->translations);

        // Verify cache key exists
        $hash = md5($text);
        $expectedKey = "{$this->prefix}:mock:{$hash}";
        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_it_uses_different_keys_for_different_providers(): void
    {
        $text = 'Hello';
        $result = new TranslationResult(['en' => 'Hello']);

        $this->mockProvider->method('translate')->willReturn($result);
        $this->mockProvider->method('getName')->willReturn('provider1');

        $this->cachingProvider->translate($text);
        $hash = md5($text);
        $this->assertTrue(Cache::has("{$this->prefix}:provider1:{$hash}"));

        $mockProvider2 = $this->createMock(TranslationProviderInterface::class);
        $mockProvider2->method('translate')->willReturn($result);
        $mockProvider2->method('getName')->willReturn('provider2');

        $cachingProvider2 = new CachingTranslationProvider($mockProvider2, $this->ttl, $this->prefix);
        $cachingProvider2->translate($text);

        $this->assertTrue(Cache::has("{$this->prefix}:provider2:{$hash}"));
        $this->assertNotEquals("{$this->prefix}:provider1:{$hash}", "{$this->prefix}:provider2:{$hash}");
    }

    public function test_it_returns_provider_name(): void
    {
        $this->mockProvider->method('getName')->willReturn('mock_name');
        $this->assertEquals('mock_name', $this->cachingProvider->getName());
    }
}
