<?php

namespace Tests\Unit\Services\Translation\Providers;

use App\DTO\TranslationResult;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\Providers\DeepLProvider;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Throwable;

class DeepLProviderTest extends TestCase
{
    private $client;
    private array $locales = ['en', 'uk', 'es'];
    private array $localeMap = ['en' => 'en-US'];
    private DeepLProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(DeepLClient::class);
        $this->provider = new DeepLProvider(
            $this->client,
            $this->locales,
            3,      // retry times
            0,      // retry sleep (0 for faster tests)
            $this->localeMap
        );
    }

    public function test_it_translates_text_into_all_locales(): void
    {
        $text = 'Apple';

        // Expect 3 calls to translateText (en, uk, es)
        $this->client->shouldReceive('translateText')
            ->times(3)
            ->withArgs(function ($t, $source, $target) use ($text) {
                return $t === $text && $source === null && in_array($target, ['en-US', 'uk', 'es']);
            })
            ->andReturn(
                (object) ['text' => 'Apple', 'detectedSourceLang' => 'en'],
                (object) ['text' => 'Яблуко', 'detectedSourceLang' => 'en'],
                (object) ['text' => 'Manzana', 'detectedSourceLang' => 'en']
            );

        $result = $this->provider->translate($text);

        $this->assertInstanceOf(TranslationResult::class, $result);
        $this->assertEquals([
            'en' => 'Apple',
            'uk' => 'Яблуко',
            'es' => 'Manzana',
        ], $result->translations);
    }

    public function test_it_maps_locales_correctly(): void
    {
        $text = 'Morning';

        // Specifically check the 'en' -> 'en-US' mapping
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andReturn((object) ['text' => 'Morning']);

        // Handle other locales to complete the loop
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'uk')
            ->once()
            ->andReturn((object) ['text' => 'Ранок']);
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'es')
            ->once()
            ->andReturn((object) ['text' => 'Mañana']);

        $result = $this->provider->translate($text);

        $this->assertEquals('Morning', $result->translations['en']);
    }

    public function test_it_throws_insufficient_funds_exception_on_456_error(): void
    {
        $text = 'Money';

        $exception = new DeepLException('Quota exceeded', 456);

        $this->client->shouldReceive('translateText')
            ->andThrow($exception);

        $this->expectException(InsufficientFundsException::class);

        $this->provider->translate($text);
    }

    public function test_it_retries_on_transient_errors_and_eventually_succeeds(): void
    {
        $text = 'Retry';

        // Fail twice, succeed once for each locale
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->times(3)
            ->andThrowExceptions([
                new DeepLException('Network error', 0),
                new DeepLException('Network error', 0),
                (object) ['text' => 'Retry'],
            ]);

        // Complete the rest
        $this->client->shouldReceive('translateText')->with($text, null, 'uk')->andReturn((object) ['text' => 'Повтор']);
        $this->client->shouldReceive('translateText')->with($text, null, 'es')->andReturn((object) ['text' => 'Reintentar']);

        $result = $this->provider->translate($text);

        $this->assertEquals('Retry', $result->translations['en']);
    }

    public function test_it_stops_retrying_on_quota_error(): void
    {
        $text = 'NoRetry';

        $exception = new DeepLException('Quota exceeded', 456);

        // Should be called only once because decider returns false for quota error
        $this->client->shouldReceive('translateText')
            ->once()
            ->andThrow($exception);

        $this->expectException(InsufficientFundsException::class);

        $this->provider->translate($text);
    }

    public function test_it_sanitizes_missing_results(): void
    {
        $text = 'Sanitize';

        // Ukrainian translation will fail/be missing
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andReturn((object) ['text' => 'Sanitize']);

        $this->client->shouldReceive('translateText')
            ->with($text, null, 'es')
            ->once()
            ->andReturn((object) ['text' => 'Sanitizar']);

        // For 'uk', simulate a failure that doesn't throw but returns empty/null
        // (Though in reality SDK throws, but we test our sanitizeResults trait resilience)
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'uk')
            ->once()
            ->andReturn((object) ['text' => '']);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, "Translation for locale 'uk' is missing"));

        $result = $this->provider->translate($text);

        $this->assertEquals('Sanitize', $result->translations['en']);
        $this->assertEquals('Sanitizar', $result->translations['es']);
        // Should contain localized fallback message
        $this->assertEquals(__('exceptions.translation.not_found', [], 'uk'), $result->translations['uk']);
    }
}
