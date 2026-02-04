<?php

namespace App\Services\Tts;

use App\Services\Tts\DTO\TtsResultDTO;
use Illuminate\Support\Facades\Storage;

class TtsStorageService
{
    public function __construct(
        private readonly string $disk,
        private readonly string $pathPrefix,
    ) {}

    /**
     * Store the synthesized audio data to disk.
     */
    public function store(TtsResultDTO $result, string $text, string $voiceId): string
    {
        $filename = $this->generateFilename($text, $voiceId, $result->format);
        $path = "{$this->pathPrefix}/{$voiceId}/{$filename}";

        Storage::disk($this->disk)->put($path, $result->audioData);

        return $path;
    }

    /**
     * Get the full URL for the stored audio file.
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Check if a synthesized audio file already exists for the given text and voice.
     */
    public function exists(string $text, string $voiceId, string $format): ?string
    {
        $filename = $this->generateFilename($text, $voiceId, $format);
        $path = "{$this->pathPrefix}/{$voiceId}/{$filename}";

        return Storage::disk($this->disk)->exists($path) ? $path : null;
    }

    /**
     * Generate a unique filename based on the text hash and voice ID.
     */
    private function generateFilename(string $text, string $voiceId, string $format): string
    {
        return md5($text.$voiceId).'.'.$format;
    }
}
