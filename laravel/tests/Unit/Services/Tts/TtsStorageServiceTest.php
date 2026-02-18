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

    public function test_store_with_path_saves_file_to_disk(): void
    {
        $result = new \App\Services\Tts\DTO\TtsResultDTO(audioData: 'audio-binary-data', format: 'mp3');

        $path = $this->service->storeWithPath($result, 'tts/test/audio.mp3');

        $this->assertEquals('tts/test/audio.mp3', $path);
        Storage::disk($this->disk)->assertExists('tts/test/audio.mp3');
    }

    public function test_exists_returns_true_when_file_exists(): void
    {
        Storage::disk($this->disk)->put('tts/existing.mp3', 'data');

        $this->assertTrue($this->service->exists('tts/existing.mp3'));
    }

    public function test_exists_returns_false_when_file_does_not_exist(): void
    {
        $this->assertFalse($this->service->exists('tts/nonexistent.mp3'));
    }
}
