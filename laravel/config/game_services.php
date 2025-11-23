<?php

use App\Contracts\GameServiceInterface;
use App\Games\TrueFalseImage\Services\TrueFalseImageService;

return [
    /**
     * Map game table prefix to service class
     *
     * @var array<string, class-string<GameServiceInterface>>
     */
    'map' => [
        'true_false_image' => TrueFalseImageService::class,
    ],

    /**
     * Default service class (optional)
     *
     * @var class-string<GameServiceInterface>|null
     */
    'default' => null,
];
