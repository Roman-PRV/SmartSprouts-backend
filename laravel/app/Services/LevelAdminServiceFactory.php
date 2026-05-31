<?php

namespace App\Services;

use App\Contracts\LevelAdminServiceInterface;
use App\Helpers\ConfigHelper;
use App\Models\Game;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the admin-side level service for a given Game by its table_prefix.
 * Mirrors GameServiceFactory but for write/admin operations.
 */
class LevelAdminServiceFactory
{
    /** @var array<string, string> */
    protected array $map;

    /**
     * @param  array<string, string>|null  $map  Optional override of the table_prefix => service-class map, used by tests.
     */
    public function __construct(protected Container $container, ?array $map = null)
    {
        $this->map = $map ?? ConfigHelper::getStringMap('game_admin_services.map', []);
    }

    /**
     * Resolve the admin service for the given game by its table_prefix.
     *
     * @throws InvalidArgumentException If no mapping exists, the configured class is missing,
     *                                  or the resolved instance does not implement the contract.
     */
    public function for(Game $game): LevelAdminServiceInterface
    {
        $key = $game->table_prefix ?? null;

        if ($key === null || ! isset($this->map[$key])) {
            throw new InvalidArgumentException("Admin operations are not configured for game prefix: {$key}");
        }

        $serviceClass = $this->map[$key];

        if (! class_exists($serviceClass)) {
            throw new InvalidArgumentException("Admin service class {$serviceClass} not found for game prefix {$key}");
        }

        $service = $this->container->make($serviceClass);

        if (! $service instanceof LevelAdminServiceInterface) {
            throw new InvalidArgumentException("{$serviceClass} must implement LevelAdminServiceInterface");
        }

        return $service;
    }
}
