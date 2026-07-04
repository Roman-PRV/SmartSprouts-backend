<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The contract the shared AbstractTrueFalseLevelAdminService relies on: a
 * storage directory to wipe on delete, plus a `statements` relation whose own
 * directories are cleaned up first. Both true/false level models satisfy it
 * (storageDirectory via the HasStorageDirectory trait), letting the base
 * service operate on either without a concrete dependency.
 */
interface TrueFalseLevelModelInterface
{
    /**
     * Directory under the upload disk holding this level's cover image.
     */
    public function storageDirectory(): string;

    /**
     * The level's statements relation; each statement owns a storage directory.
     */
    public function statements(): HasMany;
}
