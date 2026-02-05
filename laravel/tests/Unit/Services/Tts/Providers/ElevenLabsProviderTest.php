<?php

namespace Tests\Unit\Services\Tts\Providers;

use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\Providers\ElevenLabsProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ElevenLabsProviderTest extends TestCase
{
    private ElevenLabsProvider $provider;

    private $logSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logSpy = Log::spy();

        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        $this->provider = new ElevenLabsProvider(
            baseUrl: $baseUrl,
            apiKey: 'test-api-key',
            modelId: 'test-model',
            defaultVoiceId: 'test-voice',
            defaultOutputFormat: 'mp3_44100_128',
            timeout: 30,
            retryTimes: 0
        );
    }

    public function test_it_synthesizes_speech_successfully(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        $audioData = 'fake-binary-audio-content';
        $requestId = 'test-request-id';

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response($audioData, 200, [
                'request-id' => $requestId,
            ]),
        ]);

        $request = new TtsRequestDTO(
            text: 'Hello world',
            voiceId: 'voice-123'
        );

        $result = $this->provider->synthesize($request);

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);
        $this->assertEquals($requestId, $result->requestId);

        Http::assertSent(function ($request) use ($baseUrl) {
            return $request->url() === $baseUrl.'/text-to-speech/voice-123?output_format=mp3_44100_128' &&
                $request['text'] === 'Hello world' &&
                $request['model_id'] === 'test-model';
        });
    }

    public function test_it_uses_default_voice_if_none_provided(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hello');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) use ($baseUrl) {
            return $request->url() === $baseUrl.'/text-to-speech/test-voice?output_format=mp3_44100_128';
        });
    }

    public function test_it_throws_quota_exceeded_exception_on_429(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response([
                'detail' => ['message' => 'Quota reached'],
            ], 429),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test', voiceId: 'voice'));
            $this->fail('Expected TtsQuotaExceededException was not thrown.');
        } catch (TtsQuotaExceededException $e) {
            $this->assertEquals(__('exceptions.tts.elevenlabs_quota_exceeded', ['error' => 'Quota reached']), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'elevenlabs',
            'status' => 429,
            'error' => 'Quota reached',
        ]);

        $this->logSpy->shouldHaveReceived('error')->with(TtsLogEventEnum::PROVIDER_QUOTA_EXCEEDED->value, [
            'provider' => 'elevenlabs',
        ]);
    }

    public function test_it_throws_failed_exception_on_general_api_error(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response('Server Error', 500),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test', voiceId: 'voice'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.elevenlabs_failed', ['error' => 'Internal Server Error']), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'elevenlabs',
            'status' => 500,
            'error' => 'Internal Server Error',
        ]);
    }

    public function test_it_throws_failed_exception_on_empty_response(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response('', 200),
        ]);

        try {
            $this->provider->synthesize(new TtsRequestDTO(text: 'test', voiceId: 'voice'));
            $this->fail('Expected TtsFailedException was not thrown.');
        } catch (TtsFailedException $e) {
            $this->assertEquals(__('exceptions.tts.elevenlabs_empty_response'), $e->getMessage());
        }

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => 'elevenlabs',
            'status' => 200,
            'error' => 'Empty audio data received',
        ]);
    }

    public function test_it_fetches_available_voices(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/voices' => Http::response([
                'voices' => [
                    [
                        'voice_id' => 'v1',
                        'name' => 'Alice',
                        'labels' => ['language' => 'en', 'gender' => 'female'],
                    ],
                ],
            ], 200),
        ]);

        $voices = $this->provider->getAvailableVoices();

        $this->assertCount(1, $voices);
        $this->assertEquals('Alice', $voices['v1']['name']);
        $this->assertEquals('en', $voices['v1']['language']);
    }

    public function test_it_handles_missing_labels_in_voices(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/voices' => Http::response([
                'voices' => [
                    [
                        'voice_id' => 'v1',
                        'name' => 'Alice',
                        'labels' => null, // Case where labels is null
                    ],
                    [
                        'voice_id' => 'v2',
                        'name' => 'Bob',
                        // labels is missing entirely
                    ],
                ],
            ], 200),
        ]);

        $voices = $this->provider->getAvailableVoices();

        $this->assertCount(2, $voices);
        $this->assertEquals('', $voices['v1']['language']);
        $this->assertEquals('', $voices['v1']['gender']);
        $this->assertEquals('', $voices['v2']['language']);
        $this->assertEquals('', $voices['v2']['gender']);
    }

    public function test_it_returns_empty_array_if_voices_fetch_fails(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/voices' => Http::response([], 500),
        ]);

        $voices = $this->provider->getAvailableVoices();

        $this->logSpy->shouldHaveReceived('error')->once()->with(TtsLogEventEnum::VOICES_FETCH_FAILED->value, [
            'provider' => 'elevenlabs',
            'status' => 500,
        ]);

        $this->assertIsArray($voices);
        $this->assertEmpty($voices);
    }

    public function test_it_extracts_format_from_output_format(): void
    {
        $baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');

        Http::fake([
            $baseUrl.'/text-to-speech/*' => Http::response('audio', 200),
        ]);

        // PCM format
        $request = new TtsRequestDTO(
            text: 'Hello',
            outputFormat: 'pcm_44100'
        );
        $result = $this->provider->synthesize($request);
        $this->assertEquals('pcm', $result->format);

        // ULAW format
        $request = new TtsRequestDTO(
            text: 'Hello',
            outputFormat: 'ulaw_8000'
        );
        $result = $this->provider->synthesize($request);
        $this->assertEquals('ulaw', $result->format);

        // MP3 with bitrates
        $request = new TtsRequestDTO(
            text: 'Hello',
            outputFormat: 'mp3_44100_192'
        );
        $result = $this->provider->synthesize($request);
        $this->assertEquals('mp3', $result->format);
    }
}
