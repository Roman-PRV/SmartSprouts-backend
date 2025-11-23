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

        $this->app->bind(GameServiceInterface::class, function (Container $app): GameServiceInterface {
            $default = config('game_services.default', null);

            if ($default === null || ! is_string($default) || $default === '') {
                throw new RuntimeException('No default GameService configured (config.game_services.default).');
            }

            $instance = $app->make($default);

            if (! $instance instanceof GameServiceInterface) {
                throw new RuntimeException("Configured default service {$default} must implement ".GameServiceInterface::class);
            }

            return $instance;
        });
    }

    public function boot(): void
    {
        //
    }
}
