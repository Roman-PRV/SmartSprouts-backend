<?php

namespace App\Contracts;

use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

/**
 * Per-game admin operations on the game's levels (title, image, etc.).
 * Resolved by table_prefix via LevelAdminServiceFactory; one implementation per game.
 */
interface LevelAdminServiceInterface
{
    /**
     * @return Collection<int, Level>
     */
    public function list(): Collection;

    /**
     * Image is required on create — enforced by the type, not by a runtime
     * check in implementations.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $image): Level;

    /**
     * Image is optional on update — when null, the existing image is left
     * untouched. When provided, implementations replace it.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level;

    public function delete(int $levelId): void;
}
