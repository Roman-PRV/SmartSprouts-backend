<?php

namespace Tests\Unit\Services\Tts\Providers;

use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\Providers\ElevenLabsProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ElevenLabsProviderTest extends TestCase
{
    private ElevenLabsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ElevenLabsProvider(
            apiKey: 'test-api-key',
            modelId: 'test-model',
            defaultVoiceId: 'test-voice',
            timeout: 30,
            retryTimes: 0
        );
    }

    public function test_it_synthesizes_speech_successfully(): void
    {
        $audioData = 'fake-binary-audio-content';
        $requestId = 'test-request-id';

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response($audioData, 200, [
                'request-id' => $requestId,
            ]),
        ]);

        $request = new TtsRequestDTO(
            text: 'Hello world',
            voiceId: 'voice-123'
        );

        Log::shouldReceive('info')->with(TtsLogEventEnum::SYNTHESIS_STARTED->value, \Mockery::any());
        Log::shouldReceive('info')->with(TtsLogEventEnum::SYNTHESIS_SUCCESS->value, \Mockery::any());

        $result = $this->provider->synthesize($request);

        $this->assertEquals($audioData, $result->audioData);
        $this->assertEquals('mp3', $result->format);
        $this->assertEquals($requestId, $result->requestId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/voice-123' &&
                $request['text'] === 'Hello world' &&
                $request['model_id'] === 'test-model';
        });
    }

    public function test_it_uses_default_voice_if_none_provided(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('audio', 200),
        ]);

        $request = new TtsRequestDTO(text: 'Hello');
        $this->provider->synthesize($request);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/test-voice';
        });
    }

    public function test_it_throws_quota_exceeded_exception_on_429(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'detail' => ['message' => 'Quota reached'],
            ], 429),
        ]);

        $this->expectException(TtsQuotaExceededException::class);
        $this->expectExceptionMessage('ElevenLabs quota exceeded');

        $this->provider->synthesize(new TtsRequestDTO(text: 'test', voiceId: 'voice'));
    }

    public function test_it_throws_failed_exception_on_general_api_error(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(TtsFailedException::class);
        $this->expectExceptionMessage('ElevenLabs synthesis failed: Internal Server Error');

        $this->provider->synthesize(new TtsRequestDTO(text: 'test', voiceId: 'voice'));
    }

    public function test_it_fetches_available_voices(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
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

    public function test_it_returns_empty_array_if_voices_fetch_fails(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([], 500),
        ]);

        $voices = $this->provider->getAvailableVoices();

        $this->assertIsArray($voices);
        $this->assertEmpty($voices);
    }
}
