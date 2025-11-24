<?php

namespace App\Providers;

use App\Contracts\GameServiceInterface;
use App\Services\GameServiceFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameServiceFactory::class, function (Container $app): GameServiceFactory {
            $map = config('game_services.map', null);
            $default = config('game_services.default', null);

            if ($map === null) {
                $map = [];
            }

            if (! is_array($map)) {
                throw new RuntimeException('Invalid configuration: config.game_services.map must be an array.');
            }

            if ($default !== null && ! is_string($default)) {
                throw new RuntimeException('Invalid configuration: config.game_services.default must be a string or null.');
            }

            return new GameServiceFactory($app, $map, $default);
        });
        // Direct binding of GameServiceInterface is intentionally omitted.
        // Use GameServiceFactory to resolve game services as needed.
    }

    public function boot(): void
    {
        //
    }
}
