<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResourceResolver
{
    protected array $map;

    protected ?string $default;

    public function __construct(?array $map = null, ?string $default = null)
    {
        $this->map = $map ?? ConfigHelper::getStringMap('game_resources.map', []);
        $this->default = $default ?? ConfigHelper::getString('game_resources.default') ?: null;
    }

    /**
     * Return resource class name for a given Game instance using table_prefix.
     *
     * @throws InvalidArgumentException
     */
    public function resourceClassFor(Game $game): string
    {
        $key = $game->table_prefix ?? null;

        if ($key && isset($this->map[$key])) {
            return $this->map[$key];
        }

        if ($this->default) {
            return $this->default;
        }

        throw new InvalidArgumentException("No resource mapped for game table_prefix: {$key}");
    }

    /**
     * Build a JsonResource instance for a single model.
     */
    public function resourceFor(Game $game, Model $model): JsonResource
    {

        $class = $this->resourceClassFor($game);

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Resource class {$class} does not exist");
        }

        /** @var JsonResource */
        return new $class($model);
    }

    /**
     * Build a resource collection for an iterable (Eloquent Collection or array).
     */
    public function collectionFor(Game $game, Collection|array $collection): AnonymousResourceCollection
    {
        $class = $this->resourceClassFor($game);

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Resource class {$class} does not exist");
        }

        /** @var JsonResource $class */
        return $class::collection($collection);
    }
}
