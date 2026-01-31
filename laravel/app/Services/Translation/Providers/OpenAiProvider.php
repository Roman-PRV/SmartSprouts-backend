<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Services\Translation\Traits\HandlesTranslationResults;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;

class OpenAiProvider implements TranslationProviderInterface
{
    use HandlesTranslationResults;

    /**
     * @param  array<int, string>  $locales
     */
    public function __construct(
        private readonly ClientContract $client,
        private readonly array $locales,
        private readonly int $retryTimes,
        private readonly int $retrySleep,
        private readonly string $model,
        private readonly string $template,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function translate(string $text): TranslationResult
    {
        try {
            $data = retry($this->retryTimes, function () use ($text) {
                $systemPrompt = $this->buildSystemPrompt($this->locales, $this->template);

                $responseData = $this->callApi($systemPrompt, $text, $this->model);

                return $this->sanitizeResults($responseData, $this->locales);
            }, $this->retrySleep, $this->getRetryDecider());

            return new TranslationResult($data);
        } catch (\Throwable $e) {
            throw $this->handleError($e, ['input_text' => $text]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'openai';
    }

    private function getRetryDecider(): callable
    {
        return function (\Throwable $e) {
            // Do not retry on quota/billing/auth errors
            if ($this->isQuotaError($e) || $this->isAuthError($e)) {
                return false;
            }

            // Do not retry if it's already a wrapped TranslationFailedException
            if ($e instanceof TranslationFailedException) {
                return false;
            }

            // Do not retry on structural errors (like the array_map issue in SDK)
            /** @phpstan-ignore-next-line */
            if ($e instanceof \Error || $e instanceof \TypeError) {
                return false;
            }

            return true;
        };
    }

    /**
     * Build the system prompt using configured template and locales.
     *
     * @param  array<int, string>  $locales
     */
    private function buildSystemPrompt(array $locales, string $template): string
    {
        return str_replace(':locales', implode(', ', $locales), $template);
    }

    /**
     * Call the OpenAI API and return the raw data.
     *
     * @return array<string, mixed>
     */
    private function callApi(string $systemPrompt, string $text, string $model): array
    {

        // We use @ here to suppress the SDK warning "Undefined array key choices"
        // which happens when the API returns an error response that the SDK fails to parse properly.
        // The failure will still be caught and handled as a TypeError or ErrorException.
        $response = @$this->client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (empty($response->choices)) {
            throw new TranslationFailedException(__('exceptions.translation.failed').' (Empty choices)');
        }

        $content = $response->choices[0]->message->content ?? '{}';

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Centralized error handling for OpenAI service.
     *
     * @param  array<string, mixed>  $context  Додаткові дані для логів
     */
    private function handleError(\Throwable $e, array $context = []): \Throwable
    {
        if ($e instanceof ErrorException && $this->isQuotaError($e)) {
            return new InsufficientFundsException;
        }

        if ($e instanceof TransporterException) {
            Log::warning('OpenAiProvider: Network issues detected', array_merge($context, [
                'error' => $e->getMessage(),
            ]));

            return new TranslationFailedException(__('exceptions.translation.timeout'), 0, $e);
        }

        if ($e instanceof TranslationFailedException) {
            return $e;
        }

        if ($e instanceof \TypeError || $e instanceof \Error) {
            $message = $e->getMessage();
            Log::error('OpenAiProvider: SDK internal error (likely corrupt response)', array_merge($context, [
                'message' => $message,
            ]));

            // If it's a TypeError from array_map/null, it means 'choices' (or another expected key) is missing
            $isStructuralError = str_contains($message, 'array_map') && str_contains($message, 'null');
            $errorInfo = $isStructuralError ? ' (Unexpected response structure - check API Key)' : ' (Internal SDK Error)';

            return new TranslationFailedException(
                __('exceptions.translation.failed').$errorInfo,
                0,
                $e
            );
        }

        Log::error('OpenAiProvider: Unexpected translation error', array_merge($context, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));

        return new TranslationFailedException(
            $e->getMessage() ?: __('exceptions.translation.failed'),
            (int) $e->getCode(),
            $e
        );
    }

    /**
     * Determine if the API error is related to authentication.
     */
    private function isAuthError(\Throwable $e): bool
    {
        if ($e instanceof ErrorException) {
            return $e->getErrorCode() === 'invalid_api_key';
        }

        // Check message for common auth-related strings if it's not a standard ErrorException
        $message = $e->getMessage();

        return str_contains($message, 'invalid_api_key') ||
            str_contains($message, 'Unauthorized') ||
            str_contains($message, 'Unauthenticated') ||
            str_contains($message, '401');
    }

    /**
     * Determine if the API error is related to billing or quota limits.
     */
    private function isQuotaError(\Throwable $e): bool
    {
        if (! $e instanceof ErrorException) {
            return false;
        }

        $code = $e->getErrorCode();
        $type = $e->getErrorType();

        return $code === 'insufficient_quota' ||
            $code === 'billing_hard_limit_reached' ||
            $type === 'insufficient_quota';
    }
}
