<?php

namespace App\Services;

use App\Contracts\GameServiceInterface;
use App\Exceptions\GameNotConfiguredException;
use App\Helpers\ConfigHelper;
use App\Models\Game;
use Illuminate\Contracts\Container\Container;

class GameServiceFactory
{
    protected Container $container;

    protected array $map;

    protected ?string $default;

    public function __construct(Container $container, ?array $map = null, ?string $default = null)
    {
        $this->container = $container;
        $this->map = $map ?? ConfigHelper::getStringMap('games.services', []);
        $this->default = $default ?? ConfigHelper::getString('games.services_default') ?: null;
    }

    public function for(Game $game): GameServiceInterface
    {
        $key = $game->table_prefix ?? null;

        if ($key && isset($this->map[$key])) {
            $serviceClass = $this->map[$key];

            if (! class_exists($serviceClass)) {
                throw new GameNotConfiguredException("Service class {$serviceClass} not found for game prefix {$key}");
            }

            return $this->container->make($serviceClass);
        }

        if ($this->default) {
            if (! class_exists($this->default)) {
                throw new GameNotConfiguredException("Default service class {$this->default} not found");
            }

            return $this->container->make($this->default);
        }

        throw new GameNotConfiguredException("No game service configured for table prefix: {$key}");
    }
}
