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
                pathPrefix: ConfigHelper::getString('ai.tts.storage.path_prefix', 'tts/audio'),
            );
        });

        $this->app->singleton(ElevenLabsProvider::class, function () {
            return new ElevenLabsProvider(
                baseUrl: ConfigHelper::getString('ai.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'),
                apiKey: ConfigHelper::getString('ai.elevenlabs.tts.api_key'),
                modelId: ConfigHelper::getString('ai.elevenlabs.tts.model', 'eleven_multilingual_v2'),
                defaultVoiceId: ConfigHelper::getString('ai.elevenlabs.tts.voice', 'hpp4J3VqNfWAUOO0d1Us'),
                timeout: ConfigHelper::getInt('ai.elevenlabs.tts.request_timeout', 30),
                connectTimeout: ConfigHelper::getInt('ai.elevenlabs.tts.connect_timeout', 10),
                retryTimes: ConfigHelper::getInt('ai.elevenlabs.tts.retry_times', 3),
                retrySleep: ConfigHelper::getInt('ai.elevenlabs.tts.retry_sleep', 1000),
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
