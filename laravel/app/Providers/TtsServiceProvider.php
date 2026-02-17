<?php

namespace App\Providers;

use App\Contracts\TtsProviderInterface;
use App\Helpers\ConfigHelper;
use App\Services\Tts\Providers\ElevenLabsProvider;
use App\Services\Tts\TtsStorageService;
use Illuminate\Support\ServiceProvider;

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

        $this->app->singleton(\App\Contracts\Media\MediaUrlGeneratorInterface::class, \App\Services\Media\MediaUrlGenerator::class);

        $this->app->singleton(\App\Services\Tts\TtsOrchestrator::class, function ($app) {
            return new \App\Services\Tts\TtsOrchestrator(
                mediaUrlGenerator: $app->make(\App\Contracts\Media\MediaUrlGeneratorInterface::class),
                logger: \Illuminate\Support\Facades\Log::channel('tts'),
                autoGenerateEnabled: ConfigHelper::getBool('ai.tts.auto_generate.enabled', true),
                ttsDisk: ConfigHelper::getString('ai.tts.storage.disk', 'public'),
            );
        });

        $this->app->bind(TtsProviderInterface::class, ElevenLabsProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
