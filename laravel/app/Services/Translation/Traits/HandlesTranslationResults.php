<?php

namespace App\Services\Translation\Traits;

use App\DTO\TranslationItemDTO;
use App\Enums\TranslationLogEventEnum;
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

        foreach ($params->allowedLocales as $locale) {
            $value = $params->results[$locale] ?? null;

            if (\is_string($value) && trim($value) !== '') {
                $sanitized[$locale] = new TranslationItemDTO(
                    status: TranslationStatusEnum::Success,
                    text: $value
                );
            } else {
                Log::warning(TranslationLogEventEnum::LOCALE_MISSING->value, array_merge($params->context, [
                    'provider' => class_basename($this),
                    'locale' => $locale,
                    'supported_locales' => array_keys($params->results),
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
