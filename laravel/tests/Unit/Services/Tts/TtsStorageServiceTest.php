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

    public function test_it_throws_exception_if_storage_fails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to store TTS audio file to disk [{$this->disk}] at path: fail.mp3");

        // We can't easily force Storage::fake to fail, so we might need to mock the disk
        // but since we are using Storage::fake, we can just mock the underlying disk instance if needed.
        // Actually, for this specific test, we can mock the Storage facade.

        Storage::shouldReceive('disk')
            ->with($this->disk)
            ->andReturn($diskMock = \Mockery::mock());

        $diskMock->shouldReceive('put')
            ->once()
            ->andReturn(false);

        $result = new \App\Services\Tts\DTO\TtsResultDTO(audioData: 'data', format: 'mp3');
        $this->service->storeWithPath($result, 'fail.mp3');
    }
}
