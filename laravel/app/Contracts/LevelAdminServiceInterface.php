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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image): Level;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level;

    public function delete(int $levelId): void;
}
