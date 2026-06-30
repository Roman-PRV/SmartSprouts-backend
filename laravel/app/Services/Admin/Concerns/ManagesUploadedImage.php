<?php

namespace App\Services\Admin\Concerns;

use App\Helpers\ConfigHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Append-only image storage for admin level services. Shared by the true/false
 * level services, which manage a single cover image per level on the same
 * env-overridable upload disk (dev=public, prod=R2/S3). FindTheWrong predates
 * this and keeps its own copy; new services reuse this instead of re-deriving it.
 */
trait ManagesUploadedImage
{
    /**
     * Store the file under the given directory as `image.{ext}`, overwriting any
     * existing same-named file. Returns the path to persist in `image_url`.
     */
    protected function storeImage(UploadedFile $image, string $directory): string
    {
        $extension = $image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'bin';
        $filename = "image.{$extension}";

        $path = $image->storeAs($directory, $filename, $this->diskName());

        return is_string($path) ? $path : "{$directory}/{$filename}";
    }

    /**
     * Best-effort single-file delete; storage failures are logged, never thrown.
     *
     * @param  array<string, mixed>  $context
     */
    protected function deleteImageSilently(string $path, array $context = []): void
    {
        try {
            Storage::disk($this->diskName())->delete($path);
        } catch (Throwable $e) {
            Log::warning('Failed to clean replaced level image', [...$context, 'path' => $path, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Best-effort recursive directory delete; storage failures are logged.
     *
     * @param  array<string, mixed>  $context
     */
    protected function deleteDirectorySilently(string $directory, array $context = []): void
    {
        try {
            Storage::disk($this->diskName())->deleteDirectory($directory);
        } catch (Throwable $e) {
            Log::warning('Failed to clean storage directory on level delete', [...$context, 'directory' => $directory, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Read the upload disk name from config (env-overridable: dev=public, prod=s3/R2).
     */
    protected function diskName(): string
    {
        return ConfigHelper::uploadDisk();
    }
}
