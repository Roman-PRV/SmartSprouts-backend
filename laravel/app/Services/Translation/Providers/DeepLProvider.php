<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Helpers\ConfigHelper;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeepLProvider implements TranslationProviderInterface
{
    /**
     * @param  DeepLClient  $client
     * @param  array<int, string>  $locales
     * @param  int  $retryTimes
     * @param  int  $retrySleep
     * @param  array<string, string>  $localeMap
     */
    public function __construct(
        private readonly DeepLClient $client,
        private readonly array $locales,
        private readonly int $retryTimes,
        private readonly int $retrySleep,
        private readonly array $localeMap,
    ) {
    }

    /**
     * Translate the given text into all supported locales.
     *
     * @throws Throwable
     */
    public function translate(string $text): TranslationResult
    {
        $translations = [];

        foreach ($this->locales as $locale) {
            $targetLang = $this->localeMap[$locale] ?? $locale;

            try {
                $translations[$locale] = retry(
                    $this->retryTimes,
                    fn () => $this->translateSingle($text, $targetLang),
                    $this->retrySleep,
                    $this->getRetryDecider()
                );
            } catch (Throwable $e) {
                throw $this->handleError($e, [
                    'input_text' => $text,
                    'target_locale' => $locale,
                    'target_lang' => $targetLang,
                ]);
            }
        }

        $sanitized = $this->sanitizeResults($translations, $this->locales);

        return new TranslationResult($sanitized);
    }

    /**
     * Sanitize and validate translation results.
     *
     * @param  array<string, mixed>  $results
     * @param  array<int, string>  $allowedLocales
     * @return array<string, string>
     */
    private function sanitizeResults(array $results, array $allowedLocales): array
    {
        $sanitized = array_intersect_key($results, array_flip($allowedLocales));

        foreach ($allowedLocales as $locale) {
            if (! isset($sanitized[$locale]) || ! is_string($sanitized[$locale]) || trim($sanitized[$locale]) === '') {
                Log::warning("DeepLProvider: Translation for locale '{$locale}' is missing or invalid.", [
                    'locale' => $locale,
                    'available_locales' => array_keys($results),
                ]);

                $sanitized[$locale] = __('exceptions.translation.not_found', [], $locale);
            }
        }

        /** @var array<string, string> $sanitized */
        return $sanitized;
    }

    /**
     * Determine if we should retry the request.
     */
    private function getRetryDecider(): callable
    {
        return function (Throwable $e) {
            if ($e instanceof DeepLException && $this->isQuotaError($e)) {
                return false;
            }
            if ($e instanceof TranslationFailedException) {
                return false;
            }

            return true;
        };
    }

    /**
     * Translate text to a single locale.
     *
     * @throws DeepLException
     */
    private function translateSingle(string $text, string $targetLang): string
    {
        return $this->client->translateText($text, null, $targetLang)->text;
    }

    /**
     * Centralized error handling for DeepL service.
     *
     * @param  array<string, mixed>  $context
     */
    private function handleError(Throwable $e, array $context = []): Throwable
    {
        if ($e instanceof DeepLException && $this->isQuotaError($e)) {
            return new InsufficientFundsException;
        }

        if ($e instanceof TranslationFailedException) {
            return $e;
        }

        Log::error('DeepLProvider: Unexpected translation error', array_merge($context, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]));

        return new TranslationFailedException(
            $e->getMessage() ?: 'An unknown error occurred during DeepL translation.',
            (int) $e->getCode(),
            $e
        );
    }

    /**
     * Determine if the DeepL error is related to quota or billing.
     */
    private function isQuotaError(DeepLException $e): bool
    {
        // 456 is DeepL's Quota Exceeded error code
        if ($e->getCode() === 456) {
            return true;
        }

        $message = $e->getMessage();

        return str_contains($message, 'Quota exceeded');
    }
}
