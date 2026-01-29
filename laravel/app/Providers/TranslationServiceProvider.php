<?php

namespace App\Providers;

use App\Helpers\ConfigHelper;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
