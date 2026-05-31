<?php

return [
    'default_icon' => 'icons/default-icon.png',
    'default_level_image' => 'icons/default-icon.png',
    'default_icon_disk' => 'static',

    /*
    | Disk for admin-uploaded game assets (level images, etc.). Local dev
    | uses the public disk; production points at the configured cloud
    | bucket via env override.
    */
    'upload_disk' => env('GAMES_UPLOAD_DISK', 'public'),
];
