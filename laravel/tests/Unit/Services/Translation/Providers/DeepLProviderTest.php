<?php

namespace Tests\Unit\Services\Translation\Providers;

use App\DTO\TranslationResultDTO;
use App\Enums\TranslationStatusEnum;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\Providers\DeepLProvider;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

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

        $this->assertInstanceOf(TranslationResultDTO::class, $result);
        $this->assertIsString($result->requestId);

        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);
        $this->assertEquals('Apple', $result->translations['en']->text);

        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['uk']->status);
        $this->assertEquals('Яблуко', $result->translations['uk']->text);

        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['es']->status);
        $this->assertEquals('Manzana', $result->translations['es']->text);
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

        $this->assertEquals('Morning', $result->translations['en']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);
    }

    public function test_it_throws_insufficient_funds_exception_on_456_error(): void
    {
        $text = 'Money';

        $exception = new DeepLException('Quota exceeded', 456);

        // Should throw on first locale (en)
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Critical provider-level error detected'));

        $this->expectException(InsufficientFundsException::class);
        $this->provider->translate($text);
    }

    public function test_it_retries_on_transient_errors_and_eventually_succeeds(): void
    {
        $text = 'Retry';

        // Fail twice with exceptions
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->times(2)
            ->andThrow(new DeepLException('Network error', 0));

        // Succeed on third attempt
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andReturn((object) ['text' => 'Retry']);

        // Complete the rest
        $this->client->shouldReceive('translateText')->with($text, null, 'uk')->andReturn((object) ['text' => 'Повтор']);
        $this->client->shouldReceive('translateText')->with($text, null, 'es')->andReturn((object) ['text' => 'Reintentar']);

        $result = $this->provider->translate($text);

        $this->assertEquals('Retry', $result->translations['en']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);
    }

    public function test_it_stops_retrying_on_quota_error(): void
    {
        $text = 'NoRetry';

        $exception = new DeepLException('Quota exceeded', 456);

        // Should be called only once (retry decider returns false for quota errors)
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Critical provider-level error detected'));

        $this->expectException(InsufficientFundsException::class);
        $this->provider->translate($text);
    }

    public function test_it_stops_processing_locales_after_quota_error(): void
    {
        $text = 'ShortCircuit';

        $exception = new DeepLException('Quota exceeded', 456);

        // Should be called only for 'uk' (second locale), not for 'es' (third)
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andReturn((object) ['text' => 'ShortCircuit']);

        $this->client->shouldReceive('translateText')
            ->with($text, null, 'uk')
            ->once()
            ->andThrow($exception);

        // 'es' should NOT be called (short-circuit)
        $this->client->shouldNotReceive('translateText')
            ->with($text, null, 'es');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Critical provider-level error detected'));

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
        // (Though in reality SDK throws, but we test our per-locale resilience)
        $this->client->shouldReceive('translateText')
            ->with($text, null, 'uk')
            ->once()
            ->andReturn((object) ['text' => '']);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, "DeepLProvider: Translation for locale 'uk' is missing or invalid."));

        $result = $this->provider->translate($text);

        $this->assertEquals('Sanitize', $result->translations['en']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['en']->status);

        $this->assertEquals('Sanitizar', $result->translations['es']->text);
        $this->assertEquals(TranslationStatusEnum::Success, $result->translations['es']->status);

        // Should contain Error status and fallback
        $this->assertEquals(TranslationStatusEnum::Error, $result->translations['uk']->status);
        $this->assertEquals($text, $result->translations['uk']->fallback);
    }

    public function test_it_throws_translation_failed_exception_on_auth_error(): void
    {
        $text = 'AuthFail';

        $exception = new DeepLException('Unauthorized', 403);

        $this->client->shouldReceive('translateText')
            ->with($text, null, 'en-US')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Critical provider-level error detected'));

        $this->expectException(TranslationFailedException::class);
        $this->expectExceptionMessage('DeepL provider failed');
        $this->provider->translate($text);
    }
}
