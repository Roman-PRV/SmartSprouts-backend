<?php

namespace App\Services\Tts\DTO;

readonly class TtsRequestDTO
{
    public function __construct(
        public string $text,
        public string $voiceId,
        public ?string $modelId = null,
        public ?float $stability = null,
        public ?float $similarityBoost = null,
        public ?float $speed = null,
        public ?string $outputFormat = 'mp3_44100_128',
    ) {}
}
