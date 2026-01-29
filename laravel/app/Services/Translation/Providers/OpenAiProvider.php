<?php

namespace App\Services\Translation\Providers;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use App\Helpers\ConfigHelper;
use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use Throwable;

class OpenAiProvider implements TranslationProviderInterface
{
    public function __construct(
        private readonly ClientContract $client
    ) {}

    /**
     * {@inheritDoc}
     */
    public function translate(string $text): TranslationResult
    {
        $config = [
            'locales' => ConfigHelper::getStringList('app.supported_locales'),
            'times' => ConfigHelper::getInt('ai.openai.translation.retry_times', 3),
            'sleep' => ConfigHelper::getInt('ai.openai.translation.retry_sleep', 1000),
            'model' => ConfigHelper::getString('ai.openai.translation.model'),
            'template' => ConfigHelper::getString('ai.openai.translation.system_prompt'),
        ];

        try {
            $data = retry($config['times'], function () use ($text, $config) {
                $systemPrompt = $this->buildSystemPrompt($config['locales'], $config['template']);

                $responseData = $this->callApi($systemPrompt, $text, $config['model']);

                return $this->parseResponse($responseData, $config['locales']);
            }, $config['sleep'], $this->getRetryDecider());

            return new TranslationResult($data);
        } catch (Throwable $e) {
            throw $this->handleError($e, ['input_text' => $text]);
        }
    }

    private function getRetryDecider(): callable
    {
        return function (Throwable $e) {
            if ($e instanceof ErrorException && $this->isQuotaError($e)) {
                return false;
            }
            if ($e instanceof TranslationFailedException) {
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

        $response = $this->client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Parse and validate the API response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $locales
     * @return array<string, string>
     *
     * @throws TranslationFailedException
     */
    private function parseResponse(array $data, array $locales): array
    {
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            throw new TranslationFailedException(__('exceptions.translation.invalid_json'));
        }

        foreach ($locales as $locale) {
            if (! isset($data[$locale]) || ! is_string($data[$locale])) {
                throw new TranslationFailedException(__('exceptions.translation.missing_locale', ['locale' => $locale]));
            }
        }

        /** @var array<string, string> $data */
        return $data;
    }

    /**
     * Centralized error handling for OpenAI service.
     *
     * @param  array<string, mixed>  $context  Додаткові дані для логів
     */
    private function handleError(Throwable $e, array $context = []): Throwable
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

        Log::error('OpenAiProvider: Unexpected translation error', array_merge($context, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));

        return new TranslationFailedException(
            $e->getMessage() ?: 'An unknown error occurred during translation.',
            (int) $e->getCode(),
            $e
        );
    }

    /**
     * Determine if the API error is related to billing or quota limits.
     */
    private function isQuotaError(ErrorException $e): bool
    {
        $code = $e->getErrorCode();
        $type = $e->getErrorType();

        return $code === 'insufficient_quota' ||
            $code === 'billing_hard_limit_reached' ||
            $type === 'insufficient_quota';
    }
}
