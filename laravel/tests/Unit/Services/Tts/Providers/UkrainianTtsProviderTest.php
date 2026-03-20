<?php

namespace Tests\Unit\Services\Tts\Providers;

use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\Providers\UkrainianTtsProvider;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UkrainianTtsProviderTest extends TestCase
{
    private UkrainianTtsProvider $provider;

    private $logSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logSpy = Log::spy();

        $this->provider = new UkrainianTtsProvider(
            baseUrl: 'http://ukrainian-tts:5001',
            defaultSpeaker: 'lada',
            timeout: 60,
            connectTimeout: 10,
            retryTimes: 3,
            retrySleep: 100
        );
    }

    public function test_it_synthesizes_speech_successfully(): void
    {
        $audioData = 'fake-mp3-audio-content';

        Http::fake([
            'http://ukrainian-tts:5001/synthesize' => Http::response($audioData, 200),
        ]);

        $request = new TtsRequestDTO(
            text: 'Привіт, світ!',
            voiceId: 'mykyta'
        );

        $result = $this->provider->synthesize($request);

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);
        $this->assertNull($result->requestId);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://ukrainian-tts:5001/synthesize' &&
                $request['text'] === 'Привіт, світ!' &&
                $request['speaker'] === 'mykyta';
        });
    }

    public function test_it_uses_default_speaker_if_none_provided(): void
    {
        Http::fake([
            'http://ukrainian-tts:5001/synthesize' => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Тестовий текст');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://ukrainian-tts:5001/synthesize' &&
                $request['speaker'] === 'lada';
        });
    }

    public function test_it_throws_failed_exception_on_api_error(): void
    {
        Http::fake([
            'http://ukrainian-tts:5001/synthesize' => Http::response([
                'detail' => 'Model not loaded',
            ], 503),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.ukrainian_tts_failed', ['error' => 'Model not loaded']), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'ukrainian_tts',
            'status' => 503,
            'error' => 'Model not loaded',
        ]);
    }

    public function test_it_throws_failed_exception_on_empty_response(): void
    {
        Http::fake([
            'http://ukrainian-tts:5001/synthesize' => Http::response('', 200),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.ukrainian_tts_empty_response'), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'ukrainian_tts',
            'status' => 200,
            'error' => 'Empty audio data received',
        ]);
    }

    public function test_it_returns_provider_name(): void
    {
        $this->assertEquals('ukrainian_tts', $this->provider->getName());
    }

    public function test_it_returns_available_voices(): void
    {
        $voices = $this->provider->getAvailableVoices();

        $this->assertIsArray($voices);
        $this->assertArrayHasKey('lada', $voices);
        $this->assertArrayHasKey('mykyta', $voices);
        $this->assertArrayHasKey('tetiana', $voices);
        $this->assertArrayHasKey('dmytro', $voices);
        $this->assertArrayHasKey('oleksa', $voices);

        // Verify structure
        $this->assertEquals('Лада', $voices['lada']['name']);
        $this->assertEquals('uk', $voices['lada']['language']);
        $this->assertEquals('female', $voices['lada']['gender']);

        $this->assertEquals('Микита', $voices['mykyta']['name']);
        $this->assertEquals('male', $voices['mykyta']['gender']);
    }

    public function test_it_retries_on_connection_exception_and_eventually_succeeds(): void
    {
        $audioData = 'fake-audio-data';

        // Create provider with retry enabled
        $providerWithRetry = $this->createProviderWithRetry();

        // Track number of attempts
        $attemptCount = 0;

        // Mock HTTP to fail twice with ConnectException, then succeed
        Http::fake(function () use (&$attemptCount, $audioData) {
            $attemptCount++;

            // Fail first two attempts with Guzzle ConnectException
            if ($attemptCount <= 2) {
                throw new ConnectException(
                    'Connection timeout',
                    new GuzzleRequest('POST', 'test')
                );
            }

            // Succeed on third attempt
            return Http::response($audioData, 200);
        });

        $request = new TtsRequestDTO(
            text: 'Retry test',
            voiceId: 'lada'
        );

        $result = $providerWithRetry->synthesize($request);

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);

        // Verify exactly 3 requests were made (2 failures + 1 success)
        $this->assertEquals(3, $attemptCount);
    }

    public function test_it_exhausts_retries_and_throws_exception_on_persistent_connection_failure(): void
    {
        // Create provider with retry enabled
        $providerWithRetry = $this->createProviderWithRetry();

        // Track number of attempts
        $attemptCount = 0;

        // Mock HTTP to always fail with connection errors
        Http::fake(function () use (&$attemptCount) {
            $attemptCount++;
            throw new ConnectException(
                'Connection timeout',
                new GuzzleRequest('POST', 'test')
            );
        });

        $request = new TtsRequestDTO(
            text: 'Persistent failure',
            voiceId: 'lada'
        );

        try {
            $providerWithRetry->synthesize($request);
            $this->fail('Expected ConnectionException was not thrown after retry exhaustion.');
        } catch (ConnectionException $e) {
            $this->assertStringContainsString('Connection timeout', $e->getMessage());
        }

        // Verify exactly 3 requests were made (1 initial + 2 retries)
        $this->assertEquals(3, $attemptCount);
    }

    public function test_it_does_not_retry_on_non_connection_errors(): void
    {
        // Create provider with retry enabled
        $providerWithRetry = $this->createProviderWithRetry();

        // Track number of attempts
        $attemptCount = 0;

        // Mock HTTP to return 500 error (non-connection error)
        Http::fake(function () use (&$attemptCount) {
            $attemptCount++;

            return Http::response(['detail' => 'Internal error'], 500);
        });

        $request = new TtsRequestDTO(
            text: 'No retry test',
            voiceId: 'lada'
        );

        try {
            $providerWithRetry->synthesize($request);
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.ukrainian_tts_failed', ['error' => 'Internal error']), $e->getMessage());
        }

        // Verify only 1 request was made (no retries for non-connection errors)
        $this->assertEquals(1, $attemptCount);
    }

    /**
     * Create a provider instance with retry mechanism enabled.
     */
    private function createProviderWithRetry(): UkrainianTtsProvider
    {
        return new UkrainianTtsProvider(
            baseUrl: 'http://ukrainian-tts:5001',
            defaultSpeaker: 'lada',
            timeout: 60,
            connectTimeout: 10,
            retryTimes: 3,
            retrySleep: 100
        );
    }
}
