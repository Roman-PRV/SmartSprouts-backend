<?php

namespace App\Services;

use App\Contracts\StatementModelInterface;
use App\Helpers\ConfigHelper;
use App\Models\Game;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves the shared StatementAdminService for a given Game by its
 * table_prefix, binding it to that game's statement model. The statement form
 * is identical across true/false games, so a single service class is reused;
 * only the model differs, looked up in games.admin_statement_models.
 */
class StatementAdminServiceFactory
{
    /** @var array<string, string> */
    protected array $map;

    /**
     * @param  array<string, string>|null  $map  Optional override of the table_prefix => statement-model map, used by tests.
     */
    public function __construct(?array $map = null)
    {
        $this->map = $map ?? ConfigHelper::getStringMap('games.admin_statement_models', []);
    }

    /**
     * @throws InvalidArgumentException If no mapping exists or the configured class is not a valid statement model.
     */
    public function for(Game $game): StatementAdminService
    {
        $key = $game->table_prefix ?? null;

        if ($key === null || ! isset($this->map[$key])) {
            throw new InvalidArgumentException("Statement admin operations are not configured for game prefix: {$key}");
        }

        $modelClass = $this->map[$key];

        if (! is_subclass_of($modelClass, Model::class) || ! is_subclass_of($modelClass, StatementModelInterface::class)) {
            throw new InvalidArgumentException("{$modelClass} must be an Eloquent model implementing StatementModelInterface");
        }

        /** @var class-string<Model&StatementModelInterface> $modelClass */
        return new StatementAdminService($modelClass);
    }
}
