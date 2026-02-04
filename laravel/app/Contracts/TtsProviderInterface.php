<?php

namespace App\Contracts;

use App\Exceptions\Tts\TtsFailedException;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;

interface TtsProviderInterface
{
    /**
     * Synthesize speech from text.
     *
     * @throws TtsFailedException
     * @throws TtsQuotaExceededException
     */
    public function synthesize(TtsRequestDTO $request): TtsResultDTO;

    /**
     * Get the name of the provider.
     */
    public function getName(): string;

    /**
     * Get available voices for this provider.
     *
     * @return array<string, array{name: string, language: string, gender?: string}>
     */
    public function getAvailableVoices(): array;
}
