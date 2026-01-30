<?php

namespace App\Providers;

use App\Helpers\ConfigHelper;
use App\Services\Translation\Providers\DeepLProvider;
use App\Services\Translation\Providers\OpenAiProvider;
use DeepL\DeepLClient;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Contracts\ClientContract;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClientContract::class, function () {
            return OpenAI::factory()
                ->withApiKey(ConfigHelper::getString('services.openai.api_key'))
                ->withHttpClient(new Client([
                    'timeout' => config('ai.openai.translation.request_timeout'),
                    'connect_timeout' => config('ai.openai.translation.connect_timeout'),
                ]))
                ->make();
        });

        $this->app->singleton(DeepLClient::class, function () {
            return new DeepLClient(ConfigHelper::getString('services.deepl.api_key'), [
                'timeout' => config('ai.deepl.translation.request_timeout'),
                'connect_timeout' => config('ai.deepl.translation.connect_timeout'),
            ]);
        });

        $this->app->singleton(OpenAiProvider::class, function ($app) {
            return new OpenAiProvider(
                $app->make(ClientContract::class),
                ConfigHelper::getStringList('app.supported_locales'),
                ConfigHelper::getInt('ai.openai.translation.retry_times', 3),
                ConfigHelper::getInt('ai.openai.translation.retry_sleep', 1000),
                ConfigHelper::getString('ai.openai.translation.model'),
                ConfigHelper::getString('ai.openai.translation.system_prompt')
            );
        });

        $this->app->singleton(DeepLProvider::class, function ($app) {
            return new DeepLProvider(
                $app->make(DeepLClient::class),
                ConfigHelper::getStringList('app.supported_locales'),
                ConfigHelper::getInt('ai.deepl.translation.retry_times', 3),
                ConfigHelper::getInt('ai.deepl.translation.retry_sleep', 1000),
                ConfigHelper::getStringMap('ai.deepl.translation.locale_map')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
