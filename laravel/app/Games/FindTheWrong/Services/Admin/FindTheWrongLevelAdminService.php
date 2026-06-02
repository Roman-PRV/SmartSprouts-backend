<?php

namespace App\Games\FindTheWrong\Services\Admin;

use App\Contracts\LevelAdminServiceInterface;
use App\Enums\Tts\TtsModelMappingEnum;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Helpers\ConfigHelper;
use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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
     * Create a level and store its cover image. No DB transaction is used —
     * file writes cannot participate in DB transactions, so wrapping them
     * would only give a false sense of atomicity. If the upload or the
     * follow-up UPDATE fails, the row remains with NULL image_url and the
     * admin can fix it via PATCH or DELETE.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $image): Level
    {
        $level = FindTheWrongLevel::create([
            'title' => $data['title'],
        ]);

        $level->update(['image_url' => $this->storeImage($image, $level)]);

        return $level;
    }

    /**
     * Update title and, optionally, replace the cover image. The image write is
     * append-only: the new file is uploaded before the DB save, and the old
     * file is removed only after the save succeeds. This way an upload failure
     * never destroys the existing image, and a DB failure leaves only an
     * orphan file (not a broken image_url pointing at a deleted asset).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level
    {
        /** @var FindTheWrongLevel $level */
        $level = FindTheWrongLevel::query()->findOrFail($levelId);
        $oldImagePath = $level->getRawOriginal('image_url');

        $level->title = $data['title'];

        if ($image !== null) {
            $level->image_url = $this->storeImage($image, $level);
        }

        $level->save();

        if ($image !== null
            && is_string($oldImagePath)
            && $oldImagePath !== ''
            && $oldImagePath !== $level->getRawOriginal('image_url')
        ) {
            $this->deleteSilently($oldImagePath, $level->id);
        }

        return $level;
    }

    /**
     * Delete the level and explicitly cascade to items before DB deletion.
     * Database ON DELETE CASCADE would skip Eloquent events, leaving item TTS audio
     * orphaned. Instead, call ItemAdminService::delete() for each item to ensure
     * all storage cleanup runs. Then delete the level and clean its own storage.
     */
    public function delete(int $levelId): void
    {
        /** @var FindTheWrongLevel $level */
        $level = FindTheWrongLevel::query()->findOrFail($levelId);

        $itemService = app(FindTheWrongItemAdminService::class);
        foreach ($level->items as $item) {
            $itemService->delete($item->id);
        }

        $imageDir = $level->storageDirectory();
        $mapping = TtsModelMappingEnum::fromModel($level);
        $ttsDir = $mapping ? $this->getTtsDirectory($level, $mapping) : null;
        $level->delete();

        $disk = Storage::disk($this->diskName());

        try {
            $disk->deleteDirectory($imageDir);
        } catch (Throwable $e) {
            Log::warning('Failed to clean image storage on FindTheWrongLevel delete', [
                'level_id' => $levelId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($ttsDir) {
            try {
                $disk->deleteDirectory($ttsDir);
            } catch (Throwable $e) {
                Log::warning('Failed to clean TTS storage on FindTheWrongLevel delete', [
                    'level_id' => $levelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Append-only: store the new file under the level's directory without
     * touching any existing file. Returns the path to write into `image_url`.
     * Old-file cleanup is the caller's responsibility, performed only after
     * a successful DB save.
     */
    private function storeImage(UploadedFile $image, FindTheWrongLevel $level): string
    {
        $diskName = $this->diskName();
        $directory = $level->storageDirectory();

        $extension = $image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'bin';
        $filename = "image.{$extension}";

        $path = $image->storeAs($directory, $filename, $diskName);

        return is_string($path) ? $path : "{$directory}/{$filename}";
    }

    /**
     * Best-effort file delete; storage failures are logged, never thrown.
     */
    private function deleteSilently(string $path, int $levelId): void
    {
        try {
            Storage::disk($this->diskName())->delete($path);
        } catch (Throwable $e) {
            Log::warning('Failed to clean replaced image after FindTheWrongLevel update', [
                'level_id' => $levelId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the TTS storage directory path for a level based on its mapping.
     * Includes all locales and attributes under {gameType}/{entityType}/{id}/.
     */
    private function getTtsDirectory(FindTheWrongLevel $level, TtsModelMappingEnum $mapping): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            ConfigHelper::getString('ai.tts.storage.path_prefix', 'games'),
            $mapping->getGameType(),
            $mapping->getEntityType(),
            $level->getKey()
        );
    }

    /**
     * Read the upload disk name from config (env-overridable: dev=public, prod=s3/R2).
     */
    private function diskName(): string
    {
        return ConfigHelper::getString('games.upload_disk', 'public');
    }
}
