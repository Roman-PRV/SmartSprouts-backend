<?php

namespace App\Helpers;

class ConfigHelper
{
    public function getString(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_null($value)) {
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }
}
