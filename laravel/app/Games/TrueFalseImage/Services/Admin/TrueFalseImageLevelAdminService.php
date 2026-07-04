<?php

namespace App\Games\TrueFalseImage\Services\Admin;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Services\Admin\AbstractTrueFalseLevelAdminService;

/**
 * Admin-side CRUD for TrueFalseImageLevel rows (title + cover image). The shared
 * base owns all behaviour; this only names the model and the writable fields.
 * Plugs into the generic Admin\LevelController via LevelAdminServiceInterface.
 */
class TrueFalseImageLevelAdminService extends AbstractTrueFalseLevelAdminService
{
    /**
     * @return class-string<TrueFalseImageLevel>
     */
    protected function modelClass(): string
    {
        return TrueFalseImageLevel::class;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function levelAttributes(array $data): array
    {
        return ['title' => $data['title']];
    }
}
