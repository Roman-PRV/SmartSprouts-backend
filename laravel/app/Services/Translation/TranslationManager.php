<?php

namespace App\Services\Translation;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResultDTO;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslationManager implements TranslationProviderInterface
{
    public function __construct(
        private readonly TranslationProviderInterface $deepLProvider,
        private readonly TranslationProviderInterface $openAiProvider
    ) {}

    /**
     * Translate text using Failover logic: DeepL -> OpenAI.
     *
     * @throws TranslationFailedException
     * @throws InsufficientFundsException
     */
    public function translate(string $text): TranslationResultDTO
    {
        try {
            return $this->deepLProvider->translate($text);
        } catch (TranslationFailedException $e) {
            if (! $e->shouldFailover()) {
                throw $e;
            }

            Log::warning('TranslationManager: DeepL failed, switching to OpenAI.', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'text_hash' => hash('sha256', $text),
                'text_length' => mb_strlen($text),
            ]);

            return $this->openAiProvider->translate($text);
        } catch (Throwable $e) {
            // Wrap unexpected exceptions to maintain interface contract
            throw new TranslationFailedException(
                message: __('exceptions.translation.unexpected_exception'),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'manager';
    }
}
