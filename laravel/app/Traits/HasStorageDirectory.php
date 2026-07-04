<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Builds the per-record storage directory from the model's STORAGE_ROOT const
 * and primary key. Shared by every model that owns a storage folder (cover
 * image and/or TTS audio).
 *
 * Refuses an unpersisted model: without a key the path would collapse to the
 * shared root (e.g. `games/.../levels`), and handing that to
 * Storage::deleteDirectory would wipe every record's assets. The `$exists`
 * flag (true only after a successful save/find) covers all falsy-key cases.
 *
 * @mixin Model
 */
trait HasStorageDirectory
{
    /**
     * @throws LogicException
     */
    public function storageDirectory(): string
    {
        if (! $this->exists) {
            throw new LogicException(static::class.'::storageDirectory() requires a persisted model.');
        }

        return self::STORAGE_ROOT.'/'.$this->getKey();
    }
}
