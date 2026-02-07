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
        $path = $this->generateFilename($text, $voiceId, $result->format);

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
        $path = $this->generateFilename($text, $voiceId, $format);

        return Storage::disk($this->disk)->exists($path) ? $path : null;
    }

    /**
     * Generate the full storage path for the audio file.
     * Public to allow calculating once for caching logic.
     */
    public function generateFilename(string $text, string $voiceId, string $format): string
    {
        $safeVoiceId = $this->sanitize($voiceId);
        $safeFormat = $this->sanitize($format);

        return "{$this->pathPrefix}/{$safeVoiceId}/".md5($text.$safeVoiceId).".{$safeFormat}";
    }

    /**
     * Sanitize values to prevent path traversal and other filesystem issues.
     */
    private function sanitize(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9\-_]/', '', $value);
    }
}
