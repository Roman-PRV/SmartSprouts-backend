<?php

namespace App\Services\Tts\Providers;

use App\Contracts\TtsProviderInterface;
use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KokoroTtsProvider implements TtsProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $defaultVoice,
        private readonly float $speed = 1.0,
        private readonly int $timeout = 60,
        private readonly int $connectTimeout = 10,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleep = 2000,
    ) {}

    public function synthesize(TtsRequestDTO $request): TtsResultDTO
    {
        $voice = $request->voiceId ?? $this->defaultVoice;
        $url = sprintf('%s/tts', $this->baseUrl);

        Log::info(TtsLogEventEnum::SYNTHESIS_STARTED->value, [
            'provider' => $this->getName(),
            'voice' => $voice,
            'text_length' => mb_strlen($request->text),
        ]);

        $response = $this->httpClient()
            ->post($url, [
                'text' => $request->text,
                'voice' => $voice,
                'speed' => $request->speed ?? $this->speed,
            ]);

        if ($response->failed()) {
            $this->handleErrorResponse($response);
        }

        $audioData = $response->body();

        if (empty($audioData)) {
            Log::error(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
                'provider' => $this->getName(),
                'status' => $response->status(),
                'error' => 'Empty audio data received',
            ]);

            throw new TtsFailedException(
                __('exceptions.tts.kokoro_empty_response'),
                $response->status()
            );
        }

        Log::info(TtsLogEventEnum::SYNTHESIS_SUCCESS->value, [
            'provider' => $this->getName(),
            'audio_size' => strlen($audioData),
        ]);

        return new TtsResultDTO(
            audioData: $audioData,
            format: 'wav',
            requestId: null,
        );
    }

    public function getName(): string
    {
        return 'kokoro';
    }

    /**
     * Get the list of available Kokoro voices.
     *
     * @return array<string, array{name: string, language: string, gender: string}>
     */
    public function getAvailableVoices(): array
    {
        return [
            'af_heart' => [
                'name' => 'Heart',
                'language' => 'en-us',
                'gender' => 'female',
            ],
            'af_bella' => [
                'name' => 'Bella',
                'language' => 'en-us',
                'gender' => 'female',
            ],
            'am_adam' => [
                'name' => 'Adam',
                'language' => 'en-us',
                'gender' => 'male',
            ],
            'bf_emma' => [
                'name' => 'Emma',
                'language' => 'en-gb',
                'gender' => 'female',
            ],
            'bm_george' => [
                'name' => 'George',
                'language' => 'en-gb',
                'gender' => 'male',
            ],
            'ef_dora' => [
                'name' => 'Dora',
                'language' => 'es',
                'gender' => 'female',
            ],
            'em_alex' => [
                'name' => 'Alex',
                'language' => 'es',
                'gender' => 'male',
            ],
        ];
    }

    private function handleErrorResponse(Response $response): void
    {
        $status = $response->status();
        $error = $response->json('detail') ?? $response->reason();
        $errorString = is_string($error) ? $error : 'Unknown error';

        Log::error(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => $this->getName(),
            'status' => $status,
            'error' => $errorString,
        ]);

        throw new TtsFailedException(
            __('exceptions.tts.kokoro_failed', ['error' => $errorString]),
            $status
        );
    }

    /**
     * Get pre-configured HTTP client for Kokoro TTS API.
     */
    private function httpClient(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry($this->retryTimes, $this->retrySleep, function (Exception $exception) {
                return $exception instanceof ConnectionException;
            }, false);
    }
}
