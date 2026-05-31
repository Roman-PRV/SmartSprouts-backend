<?php

use App\Contracts\LevelAdminServiceInterface;
use App\Games\FindTheWrong\Services\Admin\FindTheWrongLevelAdminService;

return [
    /**
     * Map game table_prefix to admin service class for level CRUD.
     *
     * @var array<string, class-string<LevelAdminServiceInterface>>
     */
    'map' => [
        'find_the_wrong' => FindTheWrongLevelAdminService::class,
    ],
];
