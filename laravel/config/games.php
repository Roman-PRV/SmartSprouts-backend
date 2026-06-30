<?php

use App\Contracts\GameServiceInterface;
use App\Contracts\LevelAdminServiceInterface;
use App\Games\Arithmetic\Http\Resources\ArithmeticLevelResource;
use App\Games\Arithmetic\Services\AdditionTableService;
use App\Games\Arithmetic\Services\MultiplicationTableService;
use App\Games\FindTheWrong\Http\Resources\Admin\FindTheWrongLevelAdminResource;
use App\Games\FindTheWrong\Http\Resources\FindTheWrongLevelResource;
use App\Games\FindTheWrong\Services\Admin\FindTheWrongLevelAdminService;
use App\Games\FindTheWrong\Services\FindTheWrongService;
use App\Games\TrueFalseImage\Http\Resources\Admin\TrueFalseImageLevelAdminResource;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Games\TrueFalseImage\Services\Admin\TrueFalseImageLevelAdminService;
use App\Games\TrueFalseImage\Services\TrueFalseImageService;
use App\Games\TrueFalseText\Http\Resources\Admin\TrueFalseTextLevelAdminResource;
use App\Games\TrueFalseText\Http\Resources\TrueFalseTextLevelResource;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;
use App\Games\TrueFalseText\Services\Admin\TrueFalseTextLevelAdminService;
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
        'multiplication_table' => MultiplicationTableService::class,
        'addition_table' => AdditionTableService::class,
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
        'multiplication_table' => ArithmeticLevelResource::class,
        'addition_table' => ArithmeticLevelResource::class,
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
        'true_false_image' => TrueFalseImageLevelAdminService::class,
        'true_false_text' => TrueFalseTextLevelAdminService::class,
    ],

    /*
    | Per-game admin resource for the single-level read endpoint
    | (Admin\LevelController@show). The list endpoint uses the generic
    | LevelAdminResource; show returns the richer per-game shape (embedded
    | items/statements + per-locale audio status), resolved here by table_prefix.
    |
    | @var array<string, class-string<JsonResource>>
    */
    'admin_level_resources' => [
        'find_the_wrong' => FindTheWrongLevelAdminResource::class,
        'true_false_image' => TrueFalseImageLevelAdminResource::class,
        'true_false_text' => TrueFalseTextLevelAdminResource::class,
    ],

    /*
    | Per-game statement model for the shared statement admin dispatcher
    | (Admin\StatementController → StatementAdminServiceFactory). The statement
    | form is identical across true/false games, so one service drives both;
    | only the target model differs, resolved here by table_prefix.
    |
    | @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
    */
    'admin_statement_models' => [
        'true_false_image' => TrueFalseImageStatement::class,
        'true_false_text' => TrueFalseTextStatement::class,
    ],
];
