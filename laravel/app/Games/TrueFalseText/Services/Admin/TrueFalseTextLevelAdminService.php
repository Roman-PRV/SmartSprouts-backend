<?php

namespace App\Games\TrueFalseText\Services\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Models\Level;
use App\Services\Admin\Concerns\ManagesUploadedImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

/**
 * Admin-side CRUD for TrueFalseTextLevel rows. Unlike the image game, the text
 * level form carries a translatable `text` body and an *optional* cover image,
 * so it does not fit the generic LevelAdminServiceInterface (which requires an
 * image on create) and is driven by its own per-game controller instead.
 *
 * Image writes stay append-only: upload before save, remove the previous file
 * only after a successful save.
 *
 * Implements the generic LevelAdminServiceInterface so it plugs into the single
 * Admin\LevelController dispatcher alongside the other games; the divergent
 * `text` field and optional image are handled by the game-aware level request.
 */
class TrueFalseTextLevelAdminService implements LevelAdminServiceInterface
{
    use ManagesUploadedImage;

    /**
     * @return Collection<int, Level>
     */
    public function list(): Collection
    {
        /** @var Collection<int, Level> $levels */
        $levels = TrueFalseTextLevel::query()
            ->withCount('statements')
            ->orderBy('id')
            ->get();

        return $levels;
    }

    /**
     * Single level with its statements eager-loaded, for the admin editor.
     */
    public function find(int $levelId): TrueFalseTextLevel
    {
        /** @var TrueFalseTextLevel $level */
        $level = TrueFalseTextLevel::query()
            ->with('statements')
            ->findOrFail($levelId);

        return $level;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image): TrueFalseTextLevel
    {
        $level = TrueFalseTextLevel::create([
            'title' => $data['title'],
            'text' => $data['text'],
        ]);

        if ($image !== null) {
            $level->update(['image_url' => $this->storeImage($image, $level->storageDirectory())]);
        }

        return $level;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): TrueFalseTextLevel
    {
        /** @var TrueFalseTextLevel $level */
        $level = TrueFalseTextLevel::query()->findOrFail($levelId);
        $oldImagePath = $level->getRawOriginal('image_url');

        $level->title = $data['title'];
        $level->text = $data['text'];

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
        /** @var TrueFalseTextLevel $level */
        $level = TrueFalseTextLevel::query()->with('statements')->findOrFail($levelId);

        foreach ($level->statements as $statement) {
            $this->deleteDirectorySilently($statement->storageDirectory(), ['statement_id' => $statement->id]);
        }

        $directory = $level->storageDirectory();
        $level->delete();
        $this->deleteDirectorySilently($directory, ['level_id' => $levelId]);
    }
}
