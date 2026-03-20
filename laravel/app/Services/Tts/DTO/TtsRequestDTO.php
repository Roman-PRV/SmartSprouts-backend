<?php

namespace App\Services\Tts\DTO;

readonly class TtsRequestDTO
{
    public function __construct(
        public string $text,
        public ?string $locale = null,
        public ?string $voiceId = null,
        public ?string $modelId = null,
        public ?float $stability = null,
        public ?float $similarityBoost = null,
        public ?float $speed = null,
        public ?string $outputFormat = null,
    ) {}
}
