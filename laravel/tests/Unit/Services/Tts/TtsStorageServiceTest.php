<?php

namespace Tests\Unit\Services\Tts;

use App\Services\Tts\DTO\TtsResultDTO;
use App\Services\Tts\TtsStorageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TtsStorageServiceTest extends TestCase
{
    private TtsStorageService $service;

    private string $disk = 'public';

    private string $pathPrefix = 'tts/audio';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake($this->disk);
        $this->service = new TtsStorageService($this->disk, $this->pathPrefix);
    }

    public function test_it_stores_audio_data_successfully(): void
    {
        $result = new TtsResultDTO(audioData: 'binary-content', format: 'mp3');
        $text = 'Test text';
        $voiceId = 'voice-1';

        $path = $this->service->store($result, $text, $voiceId);

        $expectedFilename = md5($text.$voiceId).'.mp3';
        $expectedPath = "{$this->pathPrefix}/{$voiceId}/{$expectedFilename}";

        $this->assertEquals($expectedPath, $path);
        Storage::disk($this->disk)->assertExists($path);
        $this->assertEquals('binary-content', Storage::disk($this->disk)->get($path));
    }

    public function test_it_returns_url_for_path(): void
    {
        $path = 'tts/audio/voice-1/file.mp3';
        $fullUrl = $this->service->getUrl($path);

        $this->assertStringContainsString($path, $fullUrl);
    }

    public function test_it_checks_if_file_exists(): void
    {
        $text = 'Existing text';
        $voiceId = 'voice-1';
        $format = 'mp3';
        $filename = md5($text.$voiceId).'.mp3';
        $path = "{$this->pathPrefix}/{$voiceId}/{$filename}";

        $this->assertNull($this->service->exists($text, $voiceId, $format));

        Storage::disk($this->disk)->put($path, 'content');

        $this->assertEquals($path, $this->service->exists($text, $voiceId, $format));
    }
}
