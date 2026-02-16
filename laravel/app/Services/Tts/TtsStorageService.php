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
}
