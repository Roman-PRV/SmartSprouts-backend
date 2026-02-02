<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResultDTO;
use App\Enums\TranslationLogEventEnum;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\DTO\SanitizationParametersDTO;
use App\Services\Translation\Traits\HandlesTranslationResults;
use DeepL\DeepLClient;
use DeepL\DeepLException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeepLProvider implements TranslationProviderInterface
{
    use HandlesTranslationResults;

    /**
     * @param  array<int, string>  $locales
     * @param  array<string, string>  $localeMap
     */
    public function __construct(
        private readonly DeepLClient $client,
        private readonly array $locales,
        private readonly int $retryTimes,
        private readonly int $retrySleep,
        private readonly array $localeMap,
    ) {}

    /**
     * Translate the given text into all supported locales.
     *
     * @throws InsufficientFundsException When DeepL quota is exceeded
     * @throws TranslationFailedException When provider-level error occurs
     */
    public function translate(string $text): TranslationResultDTO
    {
        $requestId = (string) Str::uuid();
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
                if ($this->isProviderLevelError($e)) {
                    Log::error(TranslationLogEventEnum::PROVIDER_FAILED->value, [
                        'provider' => 'deepl',
                        'request_id' => $requestId,
                        'locale' => $locale,
                        'error' => $e->getMessage(),
                    ]);

                    if ($e instanceof DeepLException && $this->isQuotaError($e)) {
                        Log::error(TranslationLogEventEnum::PROVIDER_QUOTA_EXCEEDED->value, [
                            'provider' => 'deepl',
                            'request_id' => $requestId,
                            'locale' => $locale,
                        ]);

                        throw new InsufficientFundsException(
                            __('exceptions.translation.insufficient_funds').' ('.__('exceptions.translation.details.deepl_quota_exceeded').')',
                            402,
                            $e
                        );
                    }

                    throw new TranslationFailedException(
                        __('exceptions.translation.deepl_provider_failed'),
                        previous: $e
                    );
                }

                Log::info(TranslationLogEventEnum::LOCALE_FAILED->value, [
                    'provider' => 'deepl',
                    'request_id' => $requestId,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);

                $translations[$locale] = null;
            }
        }

        // If all locales failed, throw exception to enable fallback to OpenAI
        $failedCount = count(array_filter($translations, fn ($v) => $v === null));
        if ($failedCount === count($this->locales)) {
            Log::error(TranslationLogEventEnum::PROVIDER_ALL_LOCALES_FAILED->value, [
                'provider' => 'deepl',
                'request_id' => $requestId,
                'total_locales' => count($this->locales),
            ]);

            throw new TranslationFailedException(
                __('exceptions.translation.deepl_provider_failed')
            );
        }

        $sanitized = $this->sanitizeResults(new SanitizationParametersDTO(
            results: $translations,
            allowedLocales: $this->locales,
            originalText: $text,
            context: ['request_id' => $requestId]
        ));

        return new TranslationResultDTO($sanitized, $requestId);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'deepl';
    }

    /**
     * Determine if we should retry the request.
     */
    private function getRetryDecider(): callable
    {
        return function (Throwable $e) {
            if ($e instanceof DeepLException && ($this->isQuotaError($e) || $this->isAuthError($e))) {
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
     * Determine if the DeepL error is related to authentication.
     */
    private function isAuthError(DeepLException $e): bool
    {
        return $e->getCode() === 403;
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

    /**
     * Determine if the error is provider-level (should stop all translations).
     *
     * Provider-level errors include quota exceeded, authentication failures,
     * and SDK crashes. These errors should trigger failover to backup provider.
     */
    private function isProviderLevelError(Throwable $e): bool
    {
        if ($e instanceof DeepLException) {
            return $this->isQuotaError($e) || $this->isAuthError($e);
        }

        if ($e instanceof TranslationFailedException) {
            return true;
        }

        return false;
    }
}
