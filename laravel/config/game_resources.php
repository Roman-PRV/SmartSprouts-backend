<?php

use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource;
use App\Games\TrueFalseText\Http\Resources\TrueFalseTextLevelResource;
use App\Http\Resources\LevelDescriptionResource;

return [
    /*
     * Map table_prefix => resource class for single level
     */
    'map' => [
        'true_false_image' => TrueFalseImageLevelResource::class,
        'true_false_text' => TrueFalseTextLevelResource::class,
    ],

    /*
     * Optional default resource if no mapping found
     */
    'default' => LevelDescriptionResource::class,
];
