<?php

namespace App\Games\FindTheWrong\Services;

use App\Contracts\GameServiceInterface;
use App\DTO\CheckAnswersDTO;
use App\Exceptions\TableMissingException;
use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FindTheWrongService implements GameServiceInterface
{
    /**
     * Fetch all levels with the count of their items (used for the list endpoint).
     *
     * @return Collection<int, FindTheWrongLevel>
     *
     * @throws TableMissingException
     */
    public function fetchAllLevels(): Collection
    {
        $table = (new FindTheWrongLevel)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        /** @var Collection<int, FindTheWrongLevel> $levels */
        $levels = FindTheWrongLevel::query()->withCount('items')->get();

        return $levels;
    }

    /**
     * Fetch a level with eager-loaded items (used for the show endpoint).
     *
     * @throws TableMissingException
     * @throws NotFoundHttpException
     */
    public function fetchLevel(int $levelId): Level
    {
        $levelTable = (new FindTheWrongLevel)->getTable();

        if (! Schema::hasTable($levelTable)) {
            throw new TableMissingException($levelTable);
        }

        $itemsTable = (new FindTheWrongItem)->getTable();

        if (! Schema::hasTable($itemsTable)) {
            throw new TableMissingException($itemsTable);
        }

        $level = FindTheWrongLevel::with('items')->find($levelId);

        if (! $level) {
            throw new NotFoundHttpException("Level {$levelId} not found");
        }

        return $level;
    }

    /**
     * Fetch items for a level (used by the submit endpoint in WIW-BE-04).
     *
     * @return Collection<int, FindTheWrongItem>
     *
     * @throws TableMissingException
     */
    public function fetchDataForLevel(int $levelId): Collection
    {
        $table = (new FindTheWrongItem)->getTable();

        if (! Schema::hasTable($table)) {
            throw new TableMissingException($table);
        }

        /** @var Collection<int, FindTheWrongItem> $items */
        $items = FindTheWrongItem::query()->where('level_id', $levelId)->orderBy('id')->get();

        return $items;
    }

    /**
     * The legacy /check endpoint is not used by this game — scoring goes through
     * the dedicated submit endpoint introduced in WIW-BE-04.
     *
     * @return array<string, mixed>
     *
     * @throws NotFoundHttpException
     */
    public function check(CheckAnswersDTO $dto): array
    {
        throw new NotFoundHttpException(
            'The check endpoint is not supported for find_the_wrong; use the submit endpoint instead.'
        );
    }
}
