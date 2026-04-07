<?php

namespace Tests\Unit\Services\Tts;

use App\Contracts\TtsProviderInterface;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use App\Services\Tts\TtsAudioGeneratorService;
use App\Services\Tts\TtsStorageService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class TtsAudioGeneratorServiceTest extends TestCase
{
    private TtsProviderInterface&MockObject $ttsProvider;

    private TtsStorageService&MockObject $storageService;

    private LoggerInterface&MockObject $logger;

    private TtsAudioGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttsProvider = $this->createMock(TtsProviderInterface::class);
        $this->storageService = $this->createMock(TtsStorageService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new TtsAudioGeneratorService(
            $this->ttsProvider,
            $this->storageService,
            $this->logger,
        );

        config([
            'ai.tts.output_format' => 'mp3',
            'ai.tts.storage.path_prefix' => 'games',
        ]);
    }

    // ──────────────────────────────────────────────
    // Scenario 1: Audio file already exists on disk
    // ──────────────────────────────────────────────

    public function test_returns_existing_path_when_file_already_exists(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsSourceAttribute')->willReturn('statement');
        $model->method('getTtsText')->willReturn('Привіт');

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Привіт');

        $this->storageService
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->ttsProvider
            ->expects($this->never())
            ->method('synthesize');

        $model->expects($this->once())
            ->method('setAudioPath');

        $result = $this->service->generateForModel($context);

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.mp3', $result);
    }

    // ──────────────────────────────────────────────
    // Scenario 2: File does not exist → synthesis
    // ──────────────────────────────────────────────

    public function test_synthesizes_and_stores_when_file_does_not_exist(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsSourceAttribute')->willReturn('statement');

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Привіт');

        $this->storageService
            ->method('exists')
            ->willReturn(false);

        $ttsResult = new TtsResultDTO(audioData: 'binary-data', format: 'mp3');

        $this->ttsProvider
            ->expects($this->once())
            ->method('synthesize')
            ->with($this->callback(function (TtsRequestDTO $req) {
                return $req->text === 'Привіт';
            }))
            ->willReturn($ttsResult);

        $this->storageService
            ->expects($this->once())
            ->method('storeWithPath')
            ->with($ttsResult, $this->stringEndsWith('.mp3'));

        $model->expects($this->once())
            ->method('setAudioPath');

        $this->logger
            ->expects($this->once())
            ->method('info');

        $result = $this->service->generateForModel($context);

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.mp3', $result);
    }

    // ──────────────────────────────────────────────
    // Scenario 3: Text is null, model also returns null
    // ──────────────────────────────────────────────

    public function test_returns_null_and_logs_warning_when_no_text(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsText')->willReturn(null);

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', null);

        $this->ttsProvider
            ->expects($this->never())
            ->method('synthesize');

        $this->logger
            ->expects($this->once())
            ->method('warning');

        $result = $this->service->generateForModel($context);

        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────
    // Scenario 4: Text not in context, extracted from model
    // ──────────────────────────────────────────────

    public function test_extracts_text_from_model_when_not_in_context(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsSourceAttribute')->willReturn('statement');
        $model->method('getTtsText')->willReturn('Text from model');

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', null);

        $this->storageService
            ->method('exists')
            ->willReturn(false);

        $ttsResult = new TtsResultDTO(audioData: 'binary-data', format: 'mp3');

        $this->ttsProvider
            ->expects($this->once())
            ->method('synthesize')
            ->willReturn($ttsResult);

        $this->storageService
            ->expects($this->once())
            ->method('storeWithPath');

        $model->expects($this->once())
            ->method('setAudioPath');

        $result = $this->service->generateForModel($context);

        $this->assertNotNull($result);
    }

    // ──────────────────────────────────────────────
    // Scenario 5: Provider throws exception
    // ──────────────────────────────────────────────

    public function test_returns_null_and_logs_error_when_provider_throws(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsSourceAttribute')->willReturn('statement');

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Text');

        $this->storageService
            ->method('exists')
            ->willReturn(false);

        $this->ttsProvider
            ->method('synthesize')
            ->willThrowException(new \RuntimeException('Provider down'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->service->generateForModel($context);

        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────
    // Scenario 6: setAudioPath is called with correct arguments
    // ──────────────────────────────────────────────

    public function test_updates_model_audio_path_after_synthesis(): void
    {
        $model = $this->createMock(TtsMockModel::class);
        $model->method('getKey')->willReturn(42);
        $model->method('getTtsSourceAttribute')->willReturn('statement');

        $context = new TtsAudioContext($model, 'statement_audio_url', 'uk', 'Якийсь текст');

        $this->storageService
            ->method('exists')
            ->willReturn(false);

        $ttsResult = new TtsResultDTO(audioData: 'data', format: 'mp3');
        $this->ttsProvider->method('synthesize')->willReturn($ttsResult);
        $this->storageService->method('storeWithPath');

        $model->expects($this->once())
            ->method('setAudioPath')
            ->with(
                'statement_audio_url',
                'uk',
                $this->stringEndsWith('.mp3')
            );

        $this->service->generateForModel($context);
    }
}
