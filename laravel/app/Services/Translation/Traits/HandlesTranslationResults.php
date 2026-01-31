<?php

namespace App\Services\Translation\Traits;

use Illuminate\Support\Facades\Log;

trait HandlesTranslationResults
{
    /**
     * Sanitize and validate translation results.
     *
     * @param  array<string, mixed>  $results
     * @param  array<int, string>  $allowedLocales
     * @return array<string, string>
     */
    protected function sanitizeResults(array $results, array $allowedLocales): array
    {
        $sanitized = array_intersect_key($results, array_flip($allowedLocales));
        $providerName = class_basename($this);

        foreach ($allowedLocales as $locale) {
            if (! isset($sanitized[$locale]) || ! is_string($sanitized[$locale]) || trim($sanitized[$locale]) === '') {
                Log::warning("{$providerName}: Translation for locale '{$locale}' is missing or invalid.", [
                    'locale' => $locale,
                    'available_locales' => array_keys($results),
                ]);

                $sanitized[$locale] = __('exceptions.translation.not_found', [], $locale);
            }
        }

        /** @var array<string, string> $sanitized */
        return $sanitized;
    }
}
