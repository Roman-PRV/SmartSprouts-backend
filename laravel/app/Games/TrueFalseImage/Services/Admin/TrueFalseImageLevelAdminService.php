<?php

namespace App\Games\TrueFalseImage\Services\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Models\Level;
use App\Services\Admin\Concerns\ManagesUploadedImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

/**
 * Admin-side CRUD for TrueFalseImageLevel rows. The image-level form matches
 * the generic level shape (title + cover image), so this plugs into the
 * generic Admin\LevelController via LevelAdminServiceInterface — only the
 * statements relation differs from FindTheWrong (no items here).
 *
 * Image writes are append-only (upload before save, remove the old file only
 * after a successful save) so a failed upload never destroys the live image.
 */
class TrueFalseImageLevelAdminService implements LevelAdminServiceInterface
{
    use ManagesUploadedImage;

    /**
     * @return Collection<int, Level>
     */
    public function list(): Collection
    {
        /** @var Collection<int, Level> $levels */
        $levels = TrueFalseImageLevel::query()
            ->withCount('statements')
            ->orderBy('id')
            ->get();

        return $levels;
    }

    /**
     * Single level with its statements eager-loaded, for the admin editor.
     * findOrFail surfaces a 404 for the caller.
     */
    public function find(int $levelId): TrueFalseImageLevel
    {
        /** @var TrueFalseImageLevel $level */
        $level = TrueFalseImageLevel::query()
            ->with('statements')
            ->findOrFail($levelId);

        return $level;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image): Level
    {
        $level = TrueFalseImageLevel::create(['title' => $data['title']]);

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
        /** @var TrueFalseImageLevel $level */
        $level = TrueFalseImageLevel::query()->findOrFail($levelId);
        $oldImagePath = $level->getRawOriginal('image_url');

        $level->title = $data['title'];

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
        /** @var TrueFalseImageLevel $level */
        $level = TrueFalseImageLevel::query()->with('statements')->findOrFail($levelId);

        foreach ($level->statements as $statement) {
            $this->deleteDirectorySilently($statement->storageDirectory(), ['statement_id' => $statement->id]);
        }

        $directory = $level->storageDirectory();
        $level->delete();
        $this->deleteDirectorySilently($directory, ['level_id' => $levelId]);
    }
}
