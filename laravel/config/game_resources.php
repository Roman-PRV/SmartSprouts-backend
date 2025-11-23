<?php

return [
    /*
     * Map table_prefix => resource class for single level
     */
    'map' => [
        'true_false_image' => App\Games\TrueFalseImage\Http\Resources\TrueFalseImageLevelResource::class,
    ],

    /*
     * Optional default resource if no mapping found
     */
    'default' => App\Http\Resources\LevelDescriptionResource::class,
];
