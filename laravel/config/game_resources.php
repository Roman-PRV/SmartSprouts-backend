<?php

use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource;
use App\Http\Resources\LevelDescriptionResource;

return [
    /*
     * Map table_prefix => resource class for single level
     */
    'map' => [
        'true_false_image' => TrueFalseImageLevelResource::class,
    ],

    /*
     * Optional default resource if no mapping found
     */
    'default' => LevelDescriptionResource::class,
];
