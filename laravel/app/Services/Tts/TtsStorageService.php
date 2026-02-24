<?php

namespace App\Services\Tts;

use App\Services\Tts\DTO\TtsResultDTO;
use Illuminate\Support\Facades\Storage;

class TtsStorageService
{
    public function __construct(
        private readonly string $disk,
    ) {}

    /**
     * Store the synthesized audio data to disk with a custom path.
     */
    public function storeWithPath(TtsResultDTO $result, string $path): string
    {
        $stored = Storage::disk($this->disk)->put($path, $result->audioData);

        if ($stored === false) {
            throw new \RuntimeException("Failed to store TTS audio file to disk [{$this->disk}] at path: {$path}");
        }

        return $path;
    }

    /**
     * Check if a file exists on the configured disk.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}
