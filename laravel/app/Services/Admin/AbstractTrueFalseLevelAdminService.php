<?php

namespace App\Services\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Contracts\StatementModelInterface;
use App\Contracts\TrueFalseLevelModelInterface;
use App\Models\Level;
use App\Services\Admin\Concerns\ManagesUploadedImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Shared admin-side CRUD for true/false levels. The two games differ only by
 * the model and the writable fields (the text game adds a translatable `text`
 * body), so subclasses supply those via modelClass()/levelAttributes() and
 * inherit everything else — listing, eager-loading, append-only image writes,
 * and storage cleanup on delete.
 *
 * Image writes are append-only (upload before save, remove the old file only
 * after a successful save) so a failed upload never destroys the live image.
 */
abstract class AbstractTrueFalseLevelAdminService implements LevelAdminServiceInterface
{
    use ManagesUploadedImage;

    /**
     * The level model this service manages. The intersection lets the base read
     * the storage directory and statements relation off a typed query.
     *
     * @return class-string<Level&TrueFalseLevelModelInterface>
     */
    abstract protected function modelClass(): string;

    /**
     * The level attributes to persist from validated request data (image aside).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract protected function levelAttributes(array $data): array;

    /**
     * @return Collection<int, Level>
     */
    public function list(): Collection
    {
        /** @var Collection<int, Level> $levels */
        $levels = ($this->modelClass())::query()
            ->withCount('statements')
            ->orderBy('id')
            ->get();

        return $levels;
    }

    /**
     * Single level for the admin editor. Statements are NOT eager-loaded here:
     * the per-game show resource reads the relation directly (lazy-loads on
     * access), so callers that don't need statements — regenerateAudio, delete —
     * pay nothing. findOrFail surfaces a 404 for the caller.
     */
    public function find(int $levelId): Level
    {
        return ($this->modelClass())::query()->findOrFail($levelId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image): Level
    {
        /** @var Level&TrueFalseLevelModelInterface $level */
        $level = ($this->modelClass())::create($this->levelAttributes($data));

        if ($image !== null) {
            $level->update(['image_url' => $this->storeImage($image, $level->storageDirectory())]);
        }

        return $level;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level
    {
        /** @var Level&TrueFalseLevelModelInterface $level */
        $level = ($this->modelClass())::query()->findOrFail($levelId);
        $oldImagePath = $level->getRawOriginal('image_url');

        $level->fill($this->levelAttributes($data));

        if ($image !== null) {
            $level->image_url = $this->storeImage($image, $level->storageDirectory());
        }

        $level->save();

        if ($image !== null
            && is_string($oldImagePath)
            && $oldImagePath !== ''
            && $oldImagePath !== $level->getRawOriginal('image_url')
        ) {
            $this->deleteImageSilently($oldImagePath, ['level_id' => $level->id]);
        }

        return $level;
    }

    /**
     * Delete the level and wipe storage. Statement rows cascade via FK; their
     * audio directories live under a separate statements/ tree, so each is
     * wiped explicitly before the level's own directory.
     */
    public function delete(int $levelId): void
    {
        /** @var Level&TrueFalseLevelModelInterface $level */
        $level = ($this->modelClass())::query()->findOrFail($levelId);

        foreach ($level->statements()->get() as $statement) {
            /** @var StatementModelInterface&Model $statement */
            $this->deleteDirectorySilently(
                $statement->storageDirectory(),
                ['statement_id' => $statement->getKey()],
            );
        }

        $directory = $level->storageDirectory();
        $level->delete();
        $this->deleteDirectorySilently($directory, ['level_id' => $levelId]);
    }
}
