<?php

namespace App\Providers;

use App\Contracts\TranslationProviderInterface;
use App\Helpers\ConfigHelper;
use App\Services\Translation\Providers\CachingTranslationProvider;
use App\Services\Translation\Providers\DeepLProvider;
use App\Services\Translation\Providers\OpenAiProvider;
use App\Services\Translation\TranslationManager;
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
                    'timeout' => ConfigHelper::getInt('ai.openai.translation.request_timeout', 30),
                    'connect_timeout' => ConfigHelper::getInt('ai.openai.translation.connect_timeout', 10),
                ]))
                ->make();
        });

        $this->app->singleton(DeepLClient::class, function ($app) {
            return new DeepLClient(ConfigHelper::getString('services.deepl.api_key'), [
                'timeout' => ConfigHelper::getInt('ai.deepl.translation.request_timeout', 30),
                'connect_timeout' => ConfigHelper::getInt('ai.deepl.translation.connect_timeout', 10),
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

        $this->app->singleton(TranslationManager::class, function ($app) {
            $deepL = $app->make(DeepLProvider::class);
            $openAi = $app->make(OpenAiProvider::class);

            if (ConfigHelper::getBool('ai.translation.cache.enabled', true)) {
                $ttl = ConfigHelper::getInt('ai.translation.cache.ttl', 86400 * 30);
                $prefix = ConfigHelper::getString('ai.translation.cache.prefix', 'translation');

                $deepL = new CachingTranslationProvider($deepL, $ttl, $prefix);
                $openAi = new CachingTranslationProvider($openAi, $ttl, $prefix);
            }

            return new TranslationManager($deepL, $openAi);
        });

        $this->app->bind(TranslationProviderInterface::class, TranslationManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
