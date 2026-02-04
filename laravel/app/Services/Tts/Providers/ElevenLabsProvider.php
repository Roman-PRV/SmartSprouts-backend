<?php

namespace App\Services\Tts\Providers;

use App\Contracts\TtsProviderInterface;
use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsFailedException;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsProvider implements TtsProviderInterface
{
    private string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $modelId,
        private readonly string $defaultVoiceId,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleep = 1000,
    ) {
        $this->baseUrl = ConfigHelper::getString('ai.elevenlabs.base_url');
    }

    public function synthesize(TtsRequestDTO $request): TtsResultDTO
    {
        $voiceId = $request->voiceId ?: $this->defaultVoiceId;
        $url = \sprintf('%s/text-to-speech/%s', $this->baseUrl, $voiceId);

        Log::info(TtsLogEventEnum::SYNTHESIS_STARTED->value, [
            'provider' => $this->getName(),
            'voice_id' => $voiceId,
            'text_length' => mb_strlen($request->text),
        ]);

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry($this->retryTimes, $this->retrySleep, function (\Exception $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->post($url, [
                'text' => $request->text,
                'model_id' => $request->modelId ?: $this->modelId,
                'voice_settings' => [
                    'stability' => $request->stability ?? 0.5,
                    'similarity_boost' => $request->similarityBoost ?? 0.75,
                ],
            ]);

        if ($response->failed()) {
            $this->handleErrorResponse($response);
        }

        Log::info(TtsLogEventEnum::SYNTHESIS_SUCCESS->value, [
            'provider' => $this->getName(),
            'request_id' => $response->header('request-id'),
        ]);

        return new TtsResultDTO(
            audioData: $response->body(),
            format: 'mp3',
            requestId: $response->header('request-id'),
        );
    }

    public function getName(): string
    {
        return 'elevenlabs';
    }

    public function getAvailableVoices(): array
    {
        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])->get($this->baseUrl.'/voices');

        if ($response->failed()) {
            Log::error(TtsLogEventEnum::VOICES_FETCH_FAILED->value, [
                'provider' => $this->getName(),
                'status' => $response->status(),
            ]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $voices */
        $voices = $response->json('voices') ?? [];
        $result = [];

        foreach ($voices as $voice) {
            /** @var string $voiceId */
            $voiceId = \is_string($voice['voice_id'] ?? null) ? $voice['voice_id'] : '';

            /** @var string $name */
            $name = \is_string($voice['name'] ?? null) ? $voice['name'] : 'unknown';

            /** @var array<string, mixed> $labels */
            $labels = $voice['labels'] ?? [];

            /** @var string $language */
            $language = \is_string($labels['language'] ?? null) ? $labels['language'] : 'unknown';

            /** @var string $gender */
            $gender = \is_string($labels['gender'] ?? null) ? $labels['gender'] : 'unknown';

            $result[$voiceId] = [
                'name' => $name,
                'language' => $language,
                'gender' => $gender,
            ];
        }

        return $result;
    }

    private function handleErrorResponse(Response $response): void
    {
        $status = $response->status();
        /** @var string|null $error */
        $error = $response->json('detail.message') ?? $response->reason();
        $errorString = (string) ($error ?? 'Unknown error');

        Log::error(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
            'provider' => $this->getName(),
            'status' => $status,
            'error' => $errorString,
        ]);

        if ($status === 429) {
            throw new TtsQuotaExceededException(
                __('exceptions.tts.elevenlabs_quota_exceeded', ['error' => $errorString])
            );
        }

        throw new TtsFailedException(
            __('exceptions.tts.elevenlabs_failed', ['error' => $errorString]),
            $status
        );
    }
}
