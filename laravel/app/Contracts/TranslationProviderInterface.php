<?php

namespace App\Contracts;

use App\DTO\TranslationResultDTO;
use App\Exceptions\Translation\InsufficientFundsException;
use App\Exceptions\Translation\TranslationFailedException;

interface TranslationProviderInterface
{
    /**
     * Translate text into supported locales.
     *
     *
     * @throws TranslationFailedException
     * @throws InsufficientFundsException
     */
    public function translate(string $text): TranslationResultDTO;

    /**
     * Get the name of the provider.
     */
    public function getName(): string;
}
