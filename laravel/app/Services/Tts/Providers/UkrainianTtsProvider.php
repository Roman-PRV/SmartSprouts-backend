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

class UkrainianTtsProvider implements TtsProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $defaultSpeaker,
        private readonly int $timeout = 60,
        private readonly int $connectTimeout = 10,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleep = 2000,
    ) {}

    public function synthesize(TtsRequestDTO $request): TtsResultDTO
    {
        $speaker = $request->voiceId ?? $this->defaultSpeaker;
        $url = sprintf('%s/synthesize', $this->baseUrl);

        Log::info(TtsLogEventEnum::SYNTHESIS_STARTED->value, [
            'provider' => $this->getName(),
            'speaker' => $speaker,
            'text_length' => mb_strlen($request->text),
        ]);

        $response = $this->httpClient()
            ->post($url, [
                'text' => $request->text,
                'speaker' => $speaker,
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
                __('exceptions.tts.ukrainian_tts_empty_response'),
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
        return 'ukrainian_tts';
    }

    public function getAvailableVoices(): array
    {
        // Ukrainian TTS has predefined speakers
        // Based on robinhad/ukrainian-tts documentation
        return [
            'lada' => [
                'name' => 'Лада',
                'language' => 'uk',
                'gender' => 'female',
            ],
            'mykyta' => [
                'name' => 'Микита',
                'language' => 'uk',
                'gender' => 'male',
            ],
            'tetiana' => [
                'name' => 'Тетяна',
                'language' => 'uk',
                'gender' => 'female',
            ],
            'dmytro' => [
                'name' => 'Дмитро',
                'language' => 'uk',
                'gender' => 'male',
            ],
            'oleksa' => [
                'name' => 'Олекса',
                'language' => 'uk',
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
            __('exceptions.tts.ukrainian_tts_failed', ['error' => $errorString]),
            $status
        );
    }

    /**
     * Get pre-configured HTTP client for Ukrainian TTS API.
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
