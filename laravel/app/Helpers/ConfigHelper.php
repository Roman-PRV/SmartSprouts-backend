<?php

namespace App\Helpers;

class ConfigHelper
{
    /**
     * Get string from config with validation
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = \config($key);

        if (\is_null($value)) {
            return $default;
        }

        if (\is_string($value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Get required string from config. Throws exception if missing or empty.
     *
     * @throws \RuntimeException
     */
    public static function getRequiredString(string $key): string
    {
        $value = \config($key);

        if (\is_string($value) && $value !== '') {
            return $value;
        }

        throw new \RuntimeException(__('exceptions.config.required_missing', ['key' => $key]));
    }

    /**
     * Get integer from config with validation
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = \config($key);

        if (\is_int($value)) {
            return $value;
        }

        if ($value !== null) {
            $validated = \filter_var($value, FILTER_VALIDATE_INT);
            if ($validated !== false) {
                return $validated;
            }

            \Log::warning('Invalid integer value in config', [
                'key' => $key,
                'value' => $value,
            ]);
        }

        return $default;
    }

    /**
     * Get boolean from config with validation
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = \config($key);

        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            $boolValue = \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolValue !== null) {
                return $boolValue;
            }
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
        $value = \config($key);

        if (! \is_array($value)) {
            return $default;
        }

        foreach ($value as $k => $v) {
            if (! \is_string($k) || ! \is_string($v)) {
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
        $value = \config($key);

        if (! \is_array($value)) {
            return $default;
        }

        foreach ($value as $v) {
            if (! \is_string($v)) {
                return $default;
            }
        }

        return \array_values($value);
    }
}
