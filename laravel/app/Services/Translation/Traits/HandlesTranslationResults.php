<?php

namespace App\Services\Translation\Traits;

use App\DTO\TranslationItemDTO;
use App\Enums\TranslationStatusEnum;
use App\Services\Translation\DTO\SanitizationParametersDTO;
use Illuminate\Support\Facades\Log;

trait HandlesTranslationResults
{
    /**
     * Sanitize and validate translation results.
     *
     *
     * @return array<string, TranslationItemDTO>
     */
    protected function sanitizeResults(SanitizationParametersDTO $params): array
    {
        $sanitized = [];
        $providerName = class_basename($this);

        foreach ($params->allowedLocales as $locale) {
            $value = $params->results[$locale] ?? null;

            if (\is_string($value) && trim($value) !== '') {
                $sanitized[$locale] = new TranslationItemDTO(
                    status: TranslationStatusEnum::Success,
                    text: $value
                );
            } else {
                Log::warning("{$providerName}: Translation for locale '{$locale}' is missing or invalid.", array_merge($params->context, [
                    'locale' => $locale,
                    'available_locales' => array_keys($params->results),
                ]));

                $sanitized[$locale] = new TranslationItemDTO(
                    status: TranslationStatusEnum::Error,
                    fallback: $params->originalText
                );
            }
        }

        return $sanitized;
    }
}
