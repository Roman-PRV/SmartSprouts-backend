<?php

namespace Tests\Unit\Services\Tts;

use App\Services\Tts\TtsStorageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TtsStorageServiceTest extends TestCase
{
    private TtsStorageService $service;

    private string $disk = 'public';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake($this->disk);
        $this->service = new TtsStorageService($this->disk);
    }

    public function test_it_returns_url_for_path(): void
    {
        $path = 'tts/audio/voice-1/file.mp3';
        $fullUrl = $this->service->getUrl($path);

        $this->assertStringContainsString($path, $fullUrl);
    }
}
