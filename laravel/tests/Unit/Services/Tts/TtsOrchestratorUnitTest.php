<?php

namespace Tests\Unit\Services\Tts;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Contracts\TtsAudioInterface;
use App\Events\TtsAudioRequestedEvent;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\TtsOrchestrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Abstract class to help mocking intersection types TtsAudioInterface&Model
 */
abstract class TtsMockModel extends Model implements TtsAudioInterface {}

class TtsOrchestratorUnitTest extends TestCase
{
    private $mediaUrlGenerator;

    private $logger;

    private $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaUrlGenerator = $this->createMock(MediaUrlGeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        Event::fake();
    }

    public function test_get_or_generate_returns_url_if_exists()
    {
        $this->orchestrator = new TtsOrchestrator(
            $this->mediaUrlGenerator,
            $this->logger,
            true,
            'public'
        );

        $model = $this->createMock(TtsMockModel::class);
        $model->method('getTranslatableAttribute')->willReturn('path/to/audio.mp3');

        $context = new TtsAudioContext($model, 'audio', 'uk');

        $this->mediaUrlGenerator->expects($this->once())
            ->method('getUrl')
            ->with('path/to/audio.mp3', 'public')
            ->willReturn('http://example.com/audio.mp3');

        $result = $this->orchestrator->getOrGenerate($context);

        $this->assertEquals('http://example.com/audio.mp3', $result);

        Event::assertNotDispatched(TtsAudioRequestedEvent::class);
    }

    public function test_get_or_generate_triggers_dispatch_if_missing()
    {
        $this->orchestrator = new TtsOrchestrator(
            $this->mediaUrlGenerator,
            $this->logger,
            true,
            'public'
        );

        $model = $this->createMock(TtsMockModel::class);
        $model->method('getTranslatableAttribute')->willReturn(null);
        $model->method('getTtsText')->willReturn('Some text');

        $context = new TtsAudioContext($model, 'audio', 'uk');

        $this->mediaUrlGenerator->method('getUrl')->willReturn(null);

        $this->logger->expects($this->once())->method('info');

        $result = $this->orchestrator->getOrGenerate($context);

        $this->assertNull($result);

        Event::assertDispatched(TtsAudioRequestedEvent::class);
    }
}
