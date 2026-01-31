<?php

namespace App\Services\Translation;

use App\Contracts\TranslationProviderInterface;
use App\DTO\TranslationResult;
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
     * @throws Throwable
     */
    public function translate(string $text): TranslationResult
    {
        try {
            return $this->deepLProvider->translate($text);
        } catch (Throwable $e) {
            Log::warning('TranslationManager: DeepL failed, switching to OpenAI.', [
                'error' => $e->getMessage(),
                'text_preview' => mb_substr($text, 0, 50).'...',
            ]);

            return $this->openAiProvider->translate($text);
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
