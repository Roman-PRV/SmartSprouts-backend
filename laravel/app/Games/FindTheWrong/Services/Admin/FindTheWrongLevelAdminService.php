<?php

namespace App\Games\FindTheWrong\Services\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Helpers\ConfigHelper;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin-side CRUD for FindTheWrongLevel rows: persists DB changes and keeps
 * each level's storage directory in sync (image upload, full cleanup on delete).
 */
class FindTheWrongLevelAdminService implements LevelAdminServiceInterface
{
    /**
     * @return Collection<int, Level>
     */
    public function list(): Collection
    {
        /** @var Collection<int, Level> $levels */
        $levels = FindTheWrongLevel::query()
            ->withCount('items')
            ->orderBy('id')
            ->get();

        return $levels;
    }

    /**
     * Create a level and store its cover image. Runs inside a DB transaction so
     * the row + image_url update happen atomically.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException If no image is supplied.
     */
    public function create(array $data, ?UploadedFile $image): Level
    {
        if ($image === null) {
            throw new \InvalidArgumentException('Image is required when creating a find_the_wrong level.');
        }

        return DB::transaction(function () use ($data, $image): FindTheWrongLevel {
            $level = FindTheWrongLevel::create([
                'title' => $data['title'],
            ]);

            $level->update(['image_url' => $this->storeImage($image, $level)]);

            return $level->fresh() ?? $level;
        });
    }

    /**
     * Update title and, optionally, replace the cover image. When no image is
     * uploaded, the existing file is left in place untouched.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level
    {
        /** @var FindTheWrongLevel $level */
        $level = FindTheWrongLevel::query()->findOrFail($levelId);

        $level->title = $data['title'];

        if ($image !== null) {
            $level->image_url = $this->storeImage($image, $level);
        }

        $level->save();

        return $level->fresh() ?? $level;
    }

    /**
     * Delete the level (items cascade via FK) and best-effort wipe its storage
     * directory. Storage failures are logged but never block the DB deletion.
     */
    public function delete(int $levelId): void
    {
        /** @var FindTheWrongLevel $level */
        $level = FindTheWrongLevel::query()->findOrFail($levelId);
        $directory = $level->storageDirectory();
        $level->delete();

        try {
            Storage::disk($this->diskName())->deleteDirectory($directory);
        } catch (Throwable $e) {
            Log::warning('Failed to clean storage on FindTheWrongLevel delete', [
                'level_id' => $levelId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Wipe the level's storage directory and store the new file as `image.{ext}`.
     * Returns the path that should be written to `image_url`.
     */
    private function storeImage(UploadedFile $image, FindTheWrongLevel $level): string
    {
        $diskName = $this->diskName();
        $disk = Storage::disk($diskName);
        $directory = $level->storageDirectory();

        $disk->deleteDirectory($directory);

        $extension = $image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'bin';
        $filename = "image.{$extension}";

        $path = $image->storeAs($directory, $filename, $diskName);

        return is_string($path) ? $path : "{$directory}/{$filename}";
    }

    /**
     * Read the upload disk name from config (env-overridable: dev=public, prod=s3/R2).
     */
    private function diskName(): string
    {
        return ConfigHelper::getString('games.upload_disk', 'public');
    }
}
