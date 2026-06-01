<?php

use App\Contracts\GameServiceInterface;
use App\Contracts\LevelAdminServiceInterface;
use App\Games\FindTheWrong\Http\Resources\FindTheWrongLevelResource;
use App\Games\FindTheWrong\Services\Admin\FindTheWrongLevelAdminService;
use App\Games\FindTheWrong\Services\FindTheWrongService;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource;
use App\Games\TrueFalseImage\Services\TrueFalseImageService;
use App\Games\TrueFalseText\Http\Resources\TrueFalseTextLevelResource;
use App\Games\TrueFalseText\Services\TrueFalseTextService;
use App\Http\Resources\LevelDescriptionResource;
use Illuminate\Http\Resources\Json\JsonResource;

return [
    /*
    | Disk and asset defaults.
    */
    'default_icon' => 'icons/default-icon.png',
    'default_level_image' => 'icons/default-icon.png',
    'default_icon_disk' => 'static',

    /*
    | Disk for admin-uploaded game assets (level images, item TTS audio, etc.).
    | Local dev uses the public disk; production points at the configured cloud
    | bucket via env override.
    */
    'upload_disk' => env('GAMES_UPLOAD_DISK', 'public'),

    /*
    | Public game services map (read-side gameplay logic).
    |
    | @var array<string, class-string<GameServiceInterface>>
    */
    'services' => [
        'true_false_image' => TrueFalseImageService::class,
        'true_false_text' => TrueFalseTextService::class,
        'find_the_wrong' => FindTheWrongService::class,
    ],

    /*
    | Optional fallback service when no map entry matches.
    |
    | @var class-string<GameServiceInterface>|null
    */
    'services_default' => null,

    /*
    | Per-game level resource (single-level read shape).
    |
    | @var array<string, class-string<JsonResource>>
    */
    'resources' => [
        'true_false_image' => TrueFalseImageLevelResource::class,
        'true_false_text' => TrueFalseTextLevelResource::class,
        'find_the_wrong' => FindTheWrongLevelResource::class,
    ],

    /*
    | Fallback resource when no per-game mapping exists.
    |
    | @var class-string<JsonResource>|null
    */
    'resources_default' => LevelDescriptionResource::class,

    /*
    | Per-game admin services for level CRUD (write-side). Levels share an
    | identical shape across games (title + image_url + items_count), so they
    | go through Admin\LevelController dispatcher.
    |
    | Items, by contrast, differ per game (polygon for find-the-wrong,
    | is_correct for true-false, etc.) and so have per-game controllers under
    | App\Games\{Game}\Http\Controllers\Admin\ — no map needed here.
    |
    | @var array<string, class-string<LevelAdminServiceInterface>>
    */
    'admin_level_services' => [
        'find_the_wrong' => FindTheWrongLevelAdminService::class,
    ],
];
