<?php

namespace App\Games\TrueFalseText\Services\Admin;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Services\Admin\AbstractTrueFalseLevelAdminService;

/**
 * Admin-side CRUD for TrueFalseTextLevel rows (title + translatable body text +
 * optional cover image). The shared base owns all behaviour; this only names
 * the model and adds the `text` field. Plugs into the generic Admin\LevelController
 * via LevelAdminServiceInterface; image requiredness is enforced per game at the
 * request boundary, so an absent image is valid here.
 */
class TrueFalseTextLevelAdminService extends AbstractTrueFalseLevelAdminService
{
    /**
     * @return class-string<TrueFalseTextLevel>
     */
    protected function modelClass(): string
    {
        return TrueFalseTextLevel::class;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function levelAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'text' => $data['text'],
        ];
    }
}
