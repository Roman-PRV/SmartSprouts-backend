<?php

namespace App\Providers;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Contracts\TtsProviderInterface;
use App\Helpers\ConfigHelper;
use App\Listeners\GenerateMissingAudioListener;
use App\Services\Media\MediaUrlGenerator;
use App\Services\Tts\Providers\ElevenLabsProvider;
use App\Services\Tts\Providers\KokoroTtsProvider;
use App\Services\Tts\TtsAudioGeneratorService;
use App\Services\Tts\TtsOrchestrator;
use App\Services\Tts\TtsStorageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class TtsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TtsStorageService::class, function () {
            return new TtsStorageService(
                disk: ConfigHelper::getString('ai.tts.storage.disk', 'public'),
            );
        });

        $this->app->singleton(TtsAudioGeneratorService::class, function ($app) {
            return new TtsAudioGeneratorService(
                ttsProvider: $app->make(TtsProviderInterface::class),
                storageService: $app->make(TtsStorageService::class),
                logger: Log::channel('tts'),
            );
        });

        $this->app->singleton(ElevenLabsProvider::class, function () {
            return new ElevenLabsProvider(
                baseUrl: ConfigHelper::getString('ai.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'),
                apiKey: ConfigHelper::getRequiredString('ai.elevenlabs.tts.api_key'),
                modelId: ConfigHelper::getString('ai.elevenlabs.tts.model', 'eleven_multilingual_v2'),
                defaultVoiceId: ConfigHelper::getString('ai.elevenlabs.tts.voice', 'hpp4J3VqNfWAUOO0d1Us'),
                defaultOutputFormat: ConfigHelper::getString('ai.elevenlabs.tts.output_format', 'mp3_44100_128'),
                timeout: ConfigHelper::getInt('ai.elevenlabs.tts.request_timeout', 30),
                connectTimeout: ConfigHelper::getInt('ai.elevenlabs.tts.connect_timeout', 10),
                retryTimes: ConfigHelper::getInt('ai.elevenlabs.tts.retry_times', 3),
                retrySleep: ConfigHelper::getInt('ai.elevenlabs.tts.retry_sleep', 1000),
            );
        });

        $this->app->singleton(KokoroTtsProvider::class, function () {
            return new KokoroTtsProvider(
                baseUrl: ConfigHelper::getString('ai.kokoro.base_url', 'http://kokoro-tts:8880'),
                defaultVoice: ConfigHelper::getString('ai.kokoro.tts.default_voice', 'af_heart'),
                speed: (float) ConfigHelper::getString('ai.kokoro.tts.speed', '1.0'),
                timeout: ConfigHelper::getInt('ai.kokoro.tts.request_timeout', 60),
                connectTimeout: ConfigHelper::getInt('ai.kokoro.tts.connect_timeout', 10),
                retryTimes: ConfigHelper::getInt('ai.kokoro.tts.retry_times', 3),
                retrySleep: ConfigHelper::getInt('ai.kokoro.tts.retry_sleep', 2000),
            );
        });

        $this->app->singleton(MediaUrlGeneratorInterface::class, MediaUrlGenerator::class);

        $this->app->singleton(TtsOrchestrator::class, function ($app) {
            return new TtsOrchestrator(
                mediaUrlGenerator: $app->make(MediaUrlGeneratorInterface::class),
                logger: Log::channel('tts'),
                autoGenerateEnabled: ConfigHelper::getBool('ai.tts.auto_generate.enabled', true),
                ttsDisk: ConfigHelper::getString('ai.tts.storage.disk', 'public'),
            );
        });

        if ($this->app->environment('local')) {
            $this->app->bind(TtsProviderInterface::class, KokoroTtsProvider::class);
        } else {
            $this->app->bind(TtsProviderInterface::class, ElevenLabsProvider::class);
        }

        $this->app->when(GenerateMissingAudioListener::class)
            ->needs(LoggerInterface::class)
            ->give(fn () => Log::channel('tts'));
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
