<?php

namespace Tests\Unit\Jobs\Tts;

use App\Contracts\TtsAudioInterface;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\TtsAudioGeneratorService;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Abstract class to help mocking intersection types TtsAudioInterface&Model
 */
abstract class JobTtsMockModel extends Model implements TtsAudioInterface {}

class GenerateTtsAudioJobTest extends TestCase
{
    private TtsAudioGeneratorService&MockObject $audioGenerator;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audioGenerator = $this->createMock(TtsAudioGeneratorService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        config(['ai.tts.auto_generate.queue' => 'tts']);
    }

    private function createContext(): TtsAudioContext
    {
        $model = $this->createMock(JobTtsMockModel::class);
        $model->method('getKey')->willReturn(1);

        return new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Текст');
    }

    // ──────────────────────────────────────────────
    // Scenario 1: Successful generation
    // ──────────────────────────────────────────────

    public function test_calls_generate_for_model_and_logs_info(): void
    {
        $context = $this->createContext();
        $job = new GenerateTtsAudioJob($context);

        $this->audioGenerator
            ->expects($this->once())
            ->method('generateForModel')
            ->with($context);

        $this->logger
            ->expects($this->once())
            ->method('info');

        $job->handle($this->audioGenerator, $this->logger);
    }

    // ──────────────────────────────────────────────
    // Scenario 2: TtsQuotaExceededException → release
    // ──────────────────────────────────────────────

    public function test_releases_job_on_quota_exceeded(): void
    {
        $context = $this->createContext();

        /** @var GenerateTtsAudioJob&MockObject $job */
        $job = $this->getMockBuilder(GenerateTtsAudioJob::class)
            ->setConstructorArgs([$context])
            ->onlyMethods(['release', 'attempts'])
            ->getMock();

        $this->audioGenerator
            ->method('generateForModel')
            ->willThrowException(new TtsQuotaExceededException('Quota exceeded'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->logger
            ->expects($this->once())
            ->method('error');

        $job->expects($this->once())
            ->method('release');

        $job->method('attempts')->willReturn(1);

        $job->handle($this->audioGenerator, $this->logger);
    }

    // ──────────────────────────────────────────────
    // Scenario 3: Other Throwable → re-throws
    // ──────────────────────────────────────────────

    public function test_rethrows_on_generic_throwable(): void
    {
        $context = $this->createContext();
        $job = new GenerateTtsAudioJob($context);

        $this->audioGenerator
            ->method('generateForModel')
            ->willThrowException(new \RuntimeException('Provider down'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider down');

        $job->handle($this->audioGenerator, $this->logger);
    }
}
