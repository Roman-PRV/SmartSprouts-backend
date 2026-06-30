<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The full contract the shared statement admin layer relies on: translatable
 * text fields, TTS audio fields, the parent-level relation, and a storage
 * directory for cleanup. Both games' statement models satisfy it, letting
 * StatementAdminService and StatementAdminResource operate on either without a
 * concrete dependency.
 */
interface StatementModelInterface extends TranslatableStatementInterface, TtsAudioInterface
{
    /**
     * Directory under the upload disk holding this statement's TTS audio.
     */
    public function storageDirectory(): string;

    /**
     * The parent level relation (used to assert the level exists on create).
     */
    public function level(): BelongsTo;
}
