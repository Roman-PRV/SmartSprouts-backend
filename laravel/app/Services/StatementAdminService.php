<?php

namespace App\Services;

use App\Contracts\StatementModelInterface;
use App\Helpers\ConfigHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin-side CRUD for true/false statements. One service drives both games:
 * the statement form (statement + explanation translations + is_true) is
 * identical, so only the target model varies — injected by
 * StatementAdminServiceFactory per game. This is the mirror image of
 * FindTheWrong items, where the shape diverged and forced per-game controllers.
 *
 * Translatable fields are written per locale via setTranslation so locales the
 * request omits are preserved (Spatie semantics). Storage cleanup on delete
 * wipes the statement's audio directory.
 *
 * @phpstan-type StatementModel Model&StatementModelInterface
 */
class StatementAdminService
{
    /**
     * @param  class-string<Model&StatementModelInterface>  $modelClass
     */
    public function __construct(private readonly string $modelClass) {}

    /**
     * Create a statement under the given level. Asserts the parent level exists
     * first (via the model's own belongsTo relation) so a bad level_id surfaces
     * as a 404 rather than a raw FK violation.
     *
     * @param  array<string, mixed>  $data
     * @return Model&StatementModelInterface
     */
    public function create(int $levelId, array $data): Model
    {
        $statement = $this->newStatement();
        $this->assertLevelExists($statement, $levelId);

        $statement->setAttribute('level_id', $levelId);
        $statement->setAttribute('is_true', $data['is_true']);
        $this->applyTranslations($statement, $data);
        $statement->save();

        return $statement;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Model&StatementModelInterface
     */
    public function update(int $statementId, array $data): Model
    {
        $statement = $this->find($statementId);

        if (array_key_exists('is_true', $data)) {
            $statement->setAttribute('is_true', $data['is_true']);
        }

        $this->applyTranslations($statement, $data);
        $statement->save();

        return $statement;
    }

    /**
     * Delete the statement and wipe its audio directory (best-effort).
     */
    public function delete(int $statementId): void
    {
        $statement = $this->find($statementId);
        /** @var string $directory */
        $directory = $statement->storageDirectory();
        $statement->delete();

        try {
            Storage::disk($this->diskName())->deleteDirectory($directory);
        } catch (Throwable $e) {
            Log::warning('Failed to clean storage on statement delete', [
                'statement_id' => $statementId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Model&StatementModelInterface
     */
    public function find(int $statementId): Model
    {
        /** @var Model&StatementModelInterface $statement */
        $statement = $this->newStatement()->newQuery()->findOrFail($statementId);

        return $statement;
    }

    /**
     * Set each provided locale of the translatable text fields; omitted locales
     * are left untouched.
     *
     * @param  Model&StatementModelInterface  $statement
     * @param  array<string, mixed>  $data
     */
    private function applyTranslations(Model $statement, array $data): void
    {
        foreach (['statement', 'explanation'] as $field) {
            if (array_key_exists($field, $data) && is_array($data[$field])) {
                foreach ($data[$field] as $locale => $value) {
                    $statement->setTranslation($field, $locale, $value);
                }
            }
        }
    }

    /**
     * Ensure the parent level exists for THIS game before inserting. Resolves the
     * level through the statement's own belongsTo relation, so the lookup is
     * scoped to the correct game's table; a missing or foreign id surfaces as a
     * 404 instead of a raw FK violation.
     *
     * @param  Model&StatementModelInterface  $statement
     */
    private function assertLevelExists(Model $statement, int $levelId): void
    {
        $statement->level()->getRelated()->newQuery()->findOrFail($levelId);
    }

    /**
     * @return Model&StatementModelInterface
     */
    private function newStatement(): Model
    {
        return new $this->modelClass;
    }

    private function diskName(): string
    {
        return ConfigHelper::uploadDisk();
    }
}
