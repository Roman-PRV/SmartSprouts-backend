<?php

namespace Tests\Unit\Listeners;

use App\Contracts\TtsAudioInterface;
use App\Events\TtsAudioRequestedEvent;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Listeners\GenerateMissingAudioListener;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Abstract class to help mocking intersection types TtsAudioInterface&Model
 */
abstract class ListenerTtsMockModel extends Model implements TtsAudioInterface {}

class GenerateMissingAudioListenerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private GenerateMissingAudioListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new GenerateMissingAudioListener($this->logger);

        Bus::fake();
    }

    // ──────────────────────────────────────────────
    // Scenario 1: Lock acquired, model exists, no audio → dispatch job
    // ──────────────────────────────────────────────

    public function test_dispatches_job_when_lock_acquired_and_audio_missing(): void
    {
        $model = $this->createMock(ListenerTtsMockModel::class);
        $model->method('getKey')->willReturn(1);
        $model->method('getTranslatableAttribute')->willReturn(null);

        // fresh() returns the model itself (simulating model still exists)
        $model->method('fresh')->willReturn($model);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
        $event = new TtsAudioRequestedEvent($context);

        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->once()
            ->andReturn($lock);

        $this->listener->handle($event);

        Bus::assertDispatched(GenerateTtsAudioJob::class);
    }

    // ──────────────────────────────────────────────
    // Scenario 2: Lock acquired, model deleted (fresh() = null)
    // ──────────────────────────────────────────────

    public function test_does_not_dispatch_job_when_model_deleted(): void
    {
        $model = $this->createMock(ListenerTtsMockModel::class);
        $model->method('getKey')->willReturn(1);
        $model->method('fresh')->willReturn(null);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
        $event = new TtsAudioRequestedEvent($context);

        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->once()
            ->andReturn($lock);

        $this->listener->handle($event);

        Bus::assertNotDispatched(GenerateTtsAudioJob::class);
    }

    // ──────────────────────────────────────────────
    // Scenario 3: Lock acquired, audio already exists
    // ──────────────────────────────────────────────

    public function test_does_not_dispatch_job_when_audio_already_exists(): void
    {
        $model = $this->createMock(ListenerTtsMockModel::class);
        $model->method('getKey')->willReturn(1);
        $model->method('getTranslatableAttribute')->willReturn('path/to/audio.mp3');
        $model->method('fresh')->willReturn($model);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
        $event = new TtsAudioRequestedEvent($context);

        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->once()
            ->andReturn($lock);

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->listener->handle($event);

        Bus::assertNotDispatched(GenerateTtsAudioJob::class);
    }

    // ──────────────────────────────────────────────
    // Scenario 4: Lock NOT acquired
    // ──────────────────────────────────────────────

    public function test_logs_debug_and_skips_when_lock_not_acquired(): void
    {
        $model = $this->createMock(ListenerTtsMockModel::class);
        $model->method('getKey')->willReturn(1);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
        $event = new TtsAudioRequestedEvent($context);

        $lock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(false);

        Cache::shouldReceive('lock')
            ->once()
            ->andReturn($lock);

        $this->logger
            ->expects($this->once())
            ->method('debug');

        $this->listener->handle($event);

        Bus::assertNotDispatched(GenerateTtsAudioJob::class);
    }

    // ──────────────────────────────────────────────
    // Scenario 5: Exception inside handle → logged
    // ──────────────────────────────────────────────

    public function test_logs_error_when_exception_thrown(): void
    {
        $model = $this->createMock(ListenerTtsMockModel::class);
        $model->method('getKey')->willReturn(1);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
        $event = new TtsAudioRequestedEvent($context);

        Cache::shouldReceive('lock')
            ->once()
            ->andThrow(new \RuntimeException('Cache unavailable'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $this->listener->handle($event);

        Bus::assertNotDispatched(GenerateTtsAudioJob::class);
    }
}
