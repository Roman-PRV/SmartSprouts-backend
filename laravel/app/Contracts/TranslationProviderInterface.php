<?php

namespace App\Contracts;

use App\DTO\TranslationResult;
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
    public function translate(string $text): TranslationResult;

    /**
     * Get the name of the provider.
     */
    public function getName(): string;
}
