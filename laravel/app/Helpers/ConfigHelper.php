<?php

namespace App\Helpers;

class ConfigHelper
{
    public function getString(string $key, string $default = ''): string
    {
        $value = config($key);

        if (is_null($value)) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        return $default;
    }
}
