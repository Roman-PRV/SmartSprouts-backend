<?php

namespace Tests\Unit\Services\Tts\Providers;

use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\Providers\KokoroTtsProvider;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class KokoroTtsProviderTest extends TestCase
{
    private const BASE_URL = 'http://kokoro-tts:8880/tts';

    private const DEFAULT_VOICE = 'af_heart';

    private const LOCALE_VOICES = ['en' => 'af_bella', 'es' => 'ef_dora'];

    private KokoroTtsProvider $provider;

    private $logSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logSpy = Log::spy();

        $this->provider = $this->makeProvider();
    }

    private function makeProvider(int $retryTimes = 0): KokoroTtsProvider
    {
        return new KokoroTtsProvider(
            baseUrl: self::BASE_URL,
            defaultVoice: self::DEFAULT_VOICE,
            localeVoices: self::LOCALE_VOICES,
            speed: 1.0,
            timeout: 60,
            connectTimeout: 10,
            retryTimes: $retryTimes,
            retrySleep: 100,
        );
    }

    // ──────────────────────────────────────────────
    // Synthesis
    // ──────────────────────────────────────────────

    public function test_it_synthesizes_speech_successfully(): void
    {
        $audioData = 'fake-mp3-audio-content';

        Http::fake([
            self::BASE_URL => Http::response($audioData, 200),
        ]);

        $request = new TtsRequestDTO(
            text: 'Hello, how are you?',
            voiceId: 'bf_emma'
        );

        $result = $this->provider->synthesize($request);

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);
        $this->assertNull($result->requestId);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL
                && $request['text'] === 'Hello, how are you?'
                && $request['voice'] === 'bf_emma'
                && $request['speed'] === 1.0;
        });
    }

    public function test_it_uses_default_voice_if_none_provided(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hello');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL
                && $request['voice'] === self::DEFAULT_VOICE;
        });
    }

    public function test_it_uses_locale_voice_if_provided(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hola', locale: 'es');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL
                && $request['voice'] === self::LOCALE_VOICES['es'];
        });
    }

    public function test_it_falls_back_to_default_voice_if_unknown_locale_provided(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Bonjour', locale: 'fr');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request->url() === self::BASE_URL
                && $request['voice'] === self::DEFAULT_VOICE;
        });
    }

    public function test_it_uses_custom_speed_from_request(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hello', speed: 1.5);
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request['speed'] === 1.5;
        });
    }

    public function test_it_uses_default_speed_when_not_provided_in_request(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hello');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request['speed'] === 1.0;
        });
    }

    public function test_it_throws_failed_exception_on_api_error(): void
    {
        Http::fake([
            self::BASE_URL => Http::response([
                'detail' => 'Voice not found',
            ], 422),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.kokoro_failed', ['error' => 'Voice not found']), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'kokoro',
            'status' => 422,
            'error' => 'Voice not found',
        ]);
    }

    public function test_it_falls_back_to_reason_phrase_when_no_detail_in_error(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('Server Error', 500),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.kokoro_failed', ['error' => 'Internal Server Error']), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'kokoro',
            'status' => 500,
            'error' => 'Internal Server Error',
        ]);
    }

    public function test_it_throws_failed_exception_on_empty_response(): void
    {
        Http::fake([
            self::BASE_URL => Http::response('', 200),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.kokoro_empty_response'), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'kokoro',
            'status' => 200,
            'error' => 'Empty audio data received',
        ]);
    }

    // ──────────────────────────────────────────────
    // Metadata
    // ──────────────────────────────────────────────

    public function test_it_returns_provider_name(): void
    {
        $this->assertEquals('kokoro', $this->provider->getName());
    }

    public function test_it_returns_available_voices(): void
    {
        $voices = $this->provider->getAvailableVoices();

        $this->assertIsArray($voices);

        // All 7 voices are present
        $this->assertArrayHasKey('af_heart', $voices);
        $this->assertArrayHasKey('af_bella', $voices);
        $this->assertArrayHasKey('am_adam', $voices);
        $this->assertArrayHasKey('bf_emma', $voices);
        $this->assertArrayHasKey('bm_george', $voices);
        $this->assertArrayHasKey('ef_dora', $voices);
        $this->assertArrayHasKey('em_alex', $voices);

        // Verify structure and language grouping
        $this->assertEquals('en-us', $voices['af_heart']['language']);
        $this->assertEquals('female', $voices['af_heart']['gender']);

        $this->assertEquals('en-gb', $voices['bf_emma']['language']);
        $this->assertEquals('female', $voices['bf_emma']['gender']);

        $this->assertEquals('es', $voices['ef_dora']['language']);
        $this->assertEquals('female', $voices['ef_dora']['gender']);

        $this->assertEquals('es', $voices['em_alex']['language']);
        $this->assertEquals('male', $voices['em_alex']['gender']);

        $this->assertEquals('male', $voices['am_adam']['gender']);
        $this->assertEquals('male', $voices['bm_george']['gender']);
    }

    // ──────────────────────────────────────────────
    // Retry behaviour
    // ──────────────────────────────────────────────

    public function test_it_retries_on_connection_exception_and_eventually_succeeds(): void
    {
        $audioData = 'fake-mp3-audio-data';
        $providerWithRetry = $this->createProviderWithRetry();
        $attemptCount = 0;

        Http::fake(function () use (&$attemptCount, $audioData) {
            $attemptCount++;

            if ($attemptCount <= 2) {
                throw new ConnectException(
                    'Connection timeout',
                    new GuzzleRequest('POST', 'test')
                );
            }

            return Http::response($audioData, 200);
        });

        $result = $providerWithRetry->synthesize(new TtsRequestDTO(
            text: 'Hola mundo',
            voiceId: 'ef_dora'
        ));

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);
        $this->assertEquals(3, $attemptCount);
    }

    public function test_it_exhausts_retries_and_throws_exception_on_persistent_connection_failure(): void
    {
        $providerWithRetry = $this->createProviderWithRetry();
        $attemptCount = 0;

        Http::fake(function () use (&$attemptCount) {
            $attemptCount++;
            throw new ConnectException(
                'Connection timeout',
                new GuzzleRequest('POST', 'test')
            );
        });

        try {
            $providerWithRetry->synthesize(new TtsRequestDTO(text: 'Persistent failure'));
            $this->fail('Expected ConnectionException was not thrown after retry exhaustion.');
        } catch (ConnectionException $e) {
            $this->assertStringContainsString('Connection timeout', $e->getMessage());
        }

        // 1 initial + 2 retries = 3 total
        $this->assertEquals(3, $attemptCount);
    }

    public function test_it_does_not_retry_on_non_connection_errors(): void
    {
        $providerWithRetry = $this->createProviderWithRetry();
        $attemptCount = 0;

        Http::fake(function () use (&$attemptCount) {
            $attemptCount++;

            return Http::response(['detail' => 'Internal error'], 500);
        });

        try {
            $providerWithRetry->synthesize(new TtsRequestDTO(text: 'No retry test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.kokoro_failed', ['error' => 'Internal error']), $e->getMessage());
        }

        // Only 1 request — no retry for HTTP errors
        $this->assertEquals(1, $attemptCount);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function createProviderWithRetry(): KokoroTtsProvider
    {
        return $this->makeProvider(retryTimes: 3);
    }
}
