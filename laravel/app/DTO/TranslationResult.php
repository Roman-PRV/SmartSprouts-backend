<?php

namespace App\DTO;

class TranslationResult
{
    /**
     * @param  array<string, string>  $translations
     */
    public function __construct(
        public readonly array $translations
    ) {}
}
