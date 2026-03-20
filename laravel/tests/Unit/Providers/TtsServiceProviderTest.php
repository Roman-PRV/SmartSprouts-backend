<?php

namespace Tests\Unit\Providers;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Contracts\TtsProviderInterface;
use App\Events\TtsAudioRequestedEvent;
use App\Listeners\GenerateMissingAudioListener;
use App\Services\Media\MediaUrlGenerator;
use App\Services\Tts\Providers\ElevenLabsProvider;
use App\Services\Tts\Providers\KokoroTtsProvider;
use App\Services\Tts\Providers\UkrainianTtsProvider;
use App\Services\Tts\TtsAudioGeneratorService;
use App\Services\Tts\TtsOrchestrator;
use App\Services\Tts\TtsStorageService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TtsServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provide minimum required config so ElevenLabsProvider can be resolved
        // without a real API key in the test environment.
        config([
            'ai.elevenlabs.tts.api_key' => 'test-fake-api-key',
        ]);
    }

    /**
     * Re-register the TtsServiceProvider after changing the config
     * so the provider map is re-evaluated.
     */
    private function reRegisterWithProvider(string $providerKey): void
    {
        config(['ai.tts.provider' => $providerKey]);
        $this->app->register(\App\Providers\TtsServiceProvider::class, force: true);
    }

    // ──────────────────────────────────────────────
    // Event Registration
    // ──────────────────────────────────────────────

    public function test_tts_audio_requested_event_has_registered_listener(): void
    {
        $listeners = Event::getListeners(TtsAudioRequestedEvent::class);

        $this->assertNotEmpty($listeners, 'No listeners registered for TtsAudioRequestedEvent');
    }

    public function test_generate_missing_audio_listener_is_registered_for_tts_event(): void
    {
        $rawListeners = $this->app->make('events')->getRawListeners();

        $this->assertArrayHasKey(TtsAudioRequestedEvent::class, $rawListeners);

        $registeredListeners = $rawListeners[TtsAudioRequestedEvent::class];

        $listenerClasses = array_filter($registeredListeners, fn ($listener) => is_string($listener) && str_contains($listener, class_basename(GenerateMissingAudioListener::class))
        );

        $this->assertNotEmpty(
            $listenerClasses,
            sprintf('%s is not registered for %s', GenerateMissingAudioListener::class, TtsAudioRequestedEvent::class)
        );
    }

    // ──────────────────────────────────────────────
    // IoC Bindings
    // ──────────────────────────────────────────────

    public function test_tts_storage_service_resolves_as_singleton(): void
    {
        $instanceA = $this->app->make(TtsStorageService::class);
        $instanceB = $this->app->make(TtsStorageService::class);

        $this->assertInstanceOf(TtsStorageService::class, $instanceA);
        $this->assertSame($instanceA, $instanceB, 'TtsStorageService must be a singleton');
    }

    public function test_tts_audio_generator_service_resolves_as_singleton(): void
    {
        $instanceA = $this->app->make(TtsAudioGeneratorService::class);
        $instanceB = $this->app->make(TtsAudioGeneratorService::class);

        $this->assertInstanceOf(TtsAudioGeneratorService::class, $instanceA);
        $this->assertSame($instanceA, $instanceB, 'TtsAudioGeneratorService must be a singleton');
    }

    public function test_tts_orchestrator_resolves_as_singleton(): void
    {
        $instanceA = $this->app->make(TtsOrchestrator::class);
        $instanceB = $this->app->make(TtsOrchestrator::class);

        $this->assertInstanceOf(TtsOrchestrator::class, $instanceA);
        $this->assertSame($instanceA, $instanceB, 'TtsOrchestrator must be a singleton');
    }

    public function test_tts_provider_interface_resolves_to_kokoro_provider_when_configured(): void
    {
        $this->reRegisterWithProvider('kokoro');

        $provider = $this->app->make(TtsProviderInterface::class);

        $this->assertInstanceOf(KokoroTtsProvider::class, $provider);
    }

    public function test_tts_provider_interface_resolves_to_elevenlabs_provider_when_configured(): void
    {
        $this->reRegisterWithProvider('elevenlabs');

        $provider = $this->app->make(TtsProviderInterface::class);

        $this->assertInstanceOf(ElevenLabsProvider::class, $provider);
    }

    public function test_tts_provider_interface_resolves_to_ukrainian_tts_provider_when_configured(): void
    {
        $this->reRegisterWithProvider('ukrainian_tts');

        $provider = $this->app->make(TtsProviderInterface::class);

        $this->assertInstanceOf(UkrainianTtsProvider::class, $provider);
    }

    public function test_tts_provider_interface_falls_back_to_elevenlabs_on_unknown_provider_key(): void
    {
        $this->reRegisterWithProvider('nonexistent_provider');

        $provider = $this->app->make(TtsProviderInterface::class);

        $this->assertInstanceOf(ElevenLabsProvider::class, $provider);
    }

    public function test_kokoro_tts_provider_resolves_as_singleton(): void
    {
        $instanceA = $this->app->make(KokoroTtsProvider::class);
        $instanceB = $this->app->make(KokoroTtsProvider::class);

        $this->assertInstanceOf(KokoroTtsProvider::class, $instanceA);
        $this->assertSame($instanceA, $instanceB, 'KokoroTtsProvider must be a singleton');
    }

    public function test_media_url_generator_interface_resolves_to_concrete_implementation(): void
    {
        $generator = $this->app->make(MediaUrlGeneratorInterface::class);

        $this->assertInstanceOf(MediaUrlGenerator::class, $generator);
    }
}
