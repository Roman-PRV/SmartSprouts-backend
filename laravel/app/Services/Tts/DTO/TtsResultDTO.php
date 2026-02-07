<?php

namespace App\Services\Tts\DTO;

readonly class TtsResultDTO
{
    public function __construct(
        public string $audioData,
        public string $format,
        public ?string $requestId = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
        public ?string $filePath = null,
        public ?string $url = null,
    ) {}
}
