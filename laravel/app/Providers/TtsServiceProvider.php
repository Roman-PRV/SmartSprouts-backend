<?php

namespace App\Providers;

use App\Helpers\ConfigHelper;
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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
