<?php

namespace App\Helpers;

class ConfigHelper
{
    public static function getString(string $key, string $default = ''): string
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

    /**
     * Get array from config with validation
     *
     * @param  array<string, string>  $default
     * @return array<string, string>
     */
    public static function getStringMap(string $key, array $default = []): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return $default;
        }

        // Validate that all keys and values are strings
        foreach ($value as $k => $v) {
            if (! is_string($k) || ! is_string($v)) {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Get list array from config with validation
     *
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    public static function getStringList(string $key, array $default = []): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return $default;
        }

        // Validate that all values are strings (keys don't matter/can be integers)
        foreach ($value as $v) {
            if (! is_string($v)) {
                return $default;
            }
        }

        return array_values($value);
    }
}
