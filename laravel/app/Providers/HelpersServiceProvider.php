<?php

namespace App\Providers;

use App\Helpers\ConfigHelper;
use Illuminate\Support\ServiceProvider;

class HelpersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigHelper::class, function ($app) {
            return new ConfigHelper;
        });
    }

    public function boot(): void
    {
        //
    }
}
