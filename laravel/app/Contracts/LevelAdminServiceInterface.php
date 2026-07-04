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
     * Single level with its game-specific relations eager-loaded, for the admin
     * editor. findOrFail surfaces a 404 for the caller.
     */
    public function find(int $levelId): Level;

    /**
     * Image requiredness is enforced per game at the request boundary
     * (StoreLevelRequest is game-aware: required for image/find-the-wrong,
     * optional for text), so the contract accepts a nullable file. The
     * validated $data carries the per-game fields (title, plus text for the
     * text game); each implementation reads only what it needs.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image): Level;

    /**
     * Image is optional on update — when null, the existing image is left
     * untouched. When provided, implementations replace it.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $levelId, array $data, ?UploadedFile $image): Level;

    public function delete(int $levelId): void;
}
